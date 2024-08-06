<?php declare(strict_types=1);

namespace Avk\Service\Transaction;

use Avk\DataStructure\FinancialAsset;
use Avk\DataStructure\FinancialOverview;
use Avk\DataStructure\Portfolio;
use Avk\DataStructure\PortfolioTransaction;
use Avk\DataStructure\Transaction;
use Avk\Enum\Bank;
use Avk\Enum\TransactionType;
use Avk\Service\API\Eod\EodWrapper;
use Avk\Service\FileManager\CsvProcessor\Avanza;
use Avk\Service\FileManager\CsvProcessor\Custom;
use Avk\Service\FileManager\CsvProcessor\Nordnet;
use Avk\Service\FileManager\CsvProcessor\StockPrice;
use Avk\Service\Utility;
use DateTime;
use Exception;

class TransactionLoader
{
    private ?string $filterBank;
    private ?string $filterIsin;
    private ?string $filterAsset;
    private ?string $filterDateFrom;
    private ?string $filterDateTo;
    private bool $filterCurrentHoldings;
    private ?string $filterAccount;

    private TransactionMapper $transactionMapper;
    private StockPrice $stockPrice;
    public FinancialOverview $overview;

    public function __construct(
        ?string $filterBank = null,
        ?string $filterIsin = null,
        ?string $filterAsset = null,
        ?string $filterDateFrom = null,
        ?string $filterDateTo = null,
        bool $filterCurrentHoldings = false,
        ?string $filterAccount = null
    ) {
        $this->filterBank = $filterBank;
        $this->filterIsin = $filterIsin;
        $this->filterAsset = $filterAsset;
        $this->filterDateFrom = $filterDateFrom;
        $this->filterDateTo = $filterDateTo;
        $this->filterCurrentHoldings = $filterCurrentHoldings;
        $this->filterAccount = $filterAccount;

        $this->overview = new FinancialOverview();
        $this->transactionMapper = new TransactionMapper($this->overview);
        $this->stockPrice = new StockPrice();
    }

    /**
     * @param Transaction[] $transactions
     * @return FinancialAsset[]
     */
    public function getFinancialAssets(array $transactions): array
    {
        $assets = $this->transactionMapper->addTransactionsToAsset($transactions);

        if (empty($assets)) {
            throw new Exception('No transaction file in csv format in the "/imports/banks" directory.');
        }

        // Sort assets by name for readability.
        usort($assets, function (FinancialAsset $a, FinancialAsset $b): int {
            return strcasecmp($a->name, $b->name);
        });

        return $assets;
    }

    /**
     * Returns a list of sorted and possibly filtered transactions.
     * @return Transaction[]
     */
    public function getTransactions(): array
    {
        $transactions = array_merge(
            (new Avanza())->parseBankTransactions(),
            (new Nordnet())->parseBankTransactions(),
            (new Custom())->parseBankTransactions()
        );

        $transactions = $this->filterTransactions($transactions);

        if (empty($transactions)) {
            throw new Exception('No transactions found');
        }

        // Sort transactions by date, bank and ISIN. (important for calculations and handling of transactions)
        usort($transactions, function (Transaction $a, Transaction $b): int {
            $dateComparison = strtotime($a->getDateString()) <=> strtotime($b->getDateString());
            if ($dateComparison !== 0) {
                return $dateComparison;
            }

            $bankComparison = strcmp($a->getBankName(), $b->getBankName());
            if ($bankComparison !== 0) {
                return $bankComparison;
            }

            return strcmp((string) $a->isin, (string) $b->isin);
        });

        return $transactions;
    }

    /**
     * @suppress PhanUndeclaredProperty
     * @suppress PhanTypeMismatchArgumentInternalReal
     */
    public function getPortfolio(): ?Portfolio
    {
        $portfolioFile = Utility::getLatestModifiedFile(ROOT_PATH . '/resources/portfolio', 'json');
        if (!$portfolioFile) {
            // throw new Exception('No portfolio file found.');
            return null;
        }

        $rawPortfolio = Utility::jsonDecodeFromFile($portfolioFile);

        $portfolio = new Portfolio();

        if (is_object($rawPortfolio) && isset($rawPortfolio->portfolioTransactions) && is_array($rawPortfolio->portfolioTransactions)) {
            foreach ($rawPortfolio->portfolioTransactions as $rawPortfolioTransaction) {
                $portfolioTransactions = [];

                foreach ($rawPortfolioTransaction->transactions as $rawTransaction) {
                    $transaction = new Transaction(
                        new DateTime($rawTransaction->date->date),
                        Bank::from($rawTransaction->bank),
                        $rawTransaction->account,
                        TransactionType::from($rawTransaction->type),
                        $rawTransaction->name,
                        $rawTransaction->description,
                        $rawTransaction->rawQuantity,
                        $rawTransaction->rawPrice,
                        $rawTransaction->pricePerShareSEK,
                        $rawTransaction->rawAmount,
                        $rawTransaction->commission,
                        $rawTransaction->currency,
                        $rawTransaction->isin,
                        $rawTransaction->exchangeRate
                    );

                    $portfolioTransactions[] = $transaction;
                }

                $portfolio->portfolioTransactions[] = new PortfolioTransaction(
                    $rawPortfolioTransaction->name,
                    $rawPortfolioTransaction->isin,
                    $portfolioTransactions
                );
            }
        }

        if (is_object($rawPortfolio) && isset($rawPortfolio->accountTransactions) && is_array($rawPortfolio->accountTransactions)) {
            foreach ($rawPortfolio->accountTransactions as $rawAccountTransaction) {
                $transaction = new Transaction(
                    new DateTime($rawAccountTransaction->date->date),
                    Bank::from($rawAccountTransaction->bank),
                    $rawAccountTransaction->account,
                    TransactionType::from($rawAccountTransaction->type),
                    $rawAccountTransaction->name,
                    $rawAccountTransaction->description,
                    $rawAccountTransaction->rawQuantity,
                    $rawAccountTransaction->rawPrice,
                    $rawAccountTransaction->pricePerShareSEK,
                    $rawAccountTransaction->rawAmount,
                    $rawAccountTransaction->commission,
                    $rawAccountTransaction->currency,
                    $rawAccountTransaction->isin,
                    $rawAccountTransaction->exchangeRate
                );

                $portfolio->accountTransactions[] = $transaction;
            }
        }

        // $this->overview = new FinancialOverview();

        return $portfolio;
    }

    /**
     * @param FinancialAsset[] $assets
     * @param Transaction[] $transactions
     */
    public function generatePortfolio(array $assets, array $transactions): void
    {
        $files = [
            Utility::getLatestModifiedFile(IMPORT_DIR . '/banks/avanza'),
            Utility::getLatestModifiedFile(IMPORT_DIR . '/banks/nordnet'),
            Utility::getLatestModifiedFile(IMPORT_DIR . '/banks/custom')
        ];

        $string = '';
        foreach ($files as $file) {
            if ($file === null) {
                continue;
            }

            $string .= filemtime($file) . ' ' . $file;
        }

        $hash = md5($string);
        if (file_exists(ROOT_PATH . "/resources/portfolio/portfolio_{$hash}.json")) {
            return;
        }

        $assetsWithTransactions = [];
        foreach ($assets as $asset) {
            $assetsWithTransactions[] = $asset->toArray();
        }
        $nonAssetTransactions = [];
        foreach ($transactions as $transaction) {
            if (empty($transaction->isin)) {
                $nonAssetTransactions[] = $transaction;
            }
        }

        $tmpFile = ROOT_PATH . '/resources/portfolio/tmp_portfolio.json';
        $file = ROOT_PATH . "/resources/portfolio/portfolio_{$hash}.json";

        // Skriv till en temporär fil först
        $result = file_put_contents(
            $tmpFile,
            json_encode(
                [
                    'portfolioTransactions' => $assetsWithTransactions,
                    'accountTransactions' => $nonAssetTransactions
                ],
                JSON_PRETTY_PRINT
            )
        );
        if ($result === false) {
            throw new Exception('Failed to write to temp file');
        }

        // Ersätt den gamla filen med den nya datan
        if (!rename($tmpFile, $file)) {
            throw new Exception('Failed to move new file over old file');
        }

        $this->overview = new FinancialOverview(); // Reset overview
    }

    /**
     * @param Transaction[] $transactions
     * @return Transaction[] filtered transactions
     */
    public function filterTransactions(array $transactions): array
    {
        $filters = [
            'bank' => $this->filterBank,
            'isin' => $this->filterIsin,
            'asset' => $this->filterAsset,
            'dateFrom' => $this->filterDateFrom,
            'dateTo' => $this->filterDateTo,
            'currentHoldings' => $this->filterCurrentHoldings,
            'account' => $this->filterAccount
        ];

        foreach ($filters as $key => $value) {
            if (!$value) {
                continue;
            }

            $transactions = array_filter($transactions, function (Transaction $transaction) use ($key, $value): bool {
                if ($key === 'asset' && is_string($value)) {
                    // To support multiple assets
                    $assets = explode(',', mb_strtoupper($value));
                    foreach ($assets as $asset) {
                        if (str_contains(mb_strtoupper($transaction->name), trim($asset))) {
                            return true;
                        }
                    }

                    return false;
                }
                if ($key === 'account' && is_string($value)) {
                    // To support multiple accounts
                    $accounts = explode(',', mb_strtoupper($value));
                    foreach ($accounts as $account) {
                        if (str_contains(mb_strtoupper($transaction->account), trim($account))) {
                            return true;
                        }
                    }

                    return false;
                }

                if ($key === 'dateFrom' && is_string($value)) {
                    return strtotime($transaction->getDateString()) >= strtotime($value);
                }

                if ($key === 'dateTo' && is_string($value)) {
                    return strtotime($transaction->getDateString()) <= strtotime($value);
                }

                if ($key === 'currentHoldings' && $transaction->isin !== null) {
                    $currentPricePerShare = $this->stockPrice->getCurrentPriceByIsin($transaction->isin);
                    return $currentPricePerShare !== null;
                }

                if ($key === 'bank' && is_string($value)) {
                    return mb_strtoupper($transaction->getBankName()) === mb_strtoupper($value);
                }

                if ($key === 'isin' && is_string($value) && $transaction->isin !== null) {
                    return mb_strtoupper($transaction->isin) === mb_strtoupper($value);
                }

                return true;
            });
        }

        return $transactions;
    }

    /**
     * @return mixed[]
     */
    public function getHistoricalPrices(string $ticker, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $eod = new EodWrapper();

        $prices = $eod->getHistoricalPricesByTicker($ticker, $dateFrom, $dateTo);

        $historicalPrices = [];
        foreach ($prices as $price) {
            $historicalPrices[$price->date] = $price->close;
        }

        return $historicalPrices;
    }
}
