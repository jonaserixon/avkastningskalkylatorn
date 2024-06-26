<?php

namespace src\Service\Transaction;

use Exception;
use src\DataStructure\FinancialAsset;
use src\DataStructure\FinancialOverview;
use src\DataStructure\Transaction;
use src\Service\FileManager\CsvProcessor\Avanza;
use src\Service\FileManager\CsvProcessor\Nordnet;
use src\Service\FileManager\CsvProcessor\StockPrice;

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
        ?string $filterBank,
        ?string $filterIsin,
        ?string $filterAsset,
        ?string $filterDateFrom,
        ?string $filterDateTo,
        bool $filterCurrentHoldings,
        ?string $filterAccount
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
        $this->overview->firstTransactionDate = $transactions[0]->getDateString();
        $this->overview->lastTransactionDate = $transactions[count($transactions) - 1]->getDateString();

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
            (new Nordnet())->parseBankTransactions()
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
}
