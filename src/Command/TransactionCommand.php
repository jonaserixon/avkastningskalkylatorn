<?php declare(strict_types=1);

namespace src\Command;

use DateTime;
use Exception;
use src\DataStructure\FinancialAsset;
use src\DataStructure\Transaction;
use src\Enum\TransactionType;
use src\Service\FileManager\Exporter;
use src\Service\ProfitCalculator;
use src\Service\Transaction\TransactionLoader;
use src\Service\Utility;
use src\View\Logger;
use src\View\TextColorizer;
use stdClass;

class TransactionCommand extends CommandProcessor
{
    /** @var mixed[] */
    private array $options;

    /**
     * @param mixed[] $options
     */
    public function __construct(array $options)
    {
        $this->options = $options;

        parent::__construct();
    }

    public function getParsedOptions(): stdClass
    {
        $commandOptions = $this->commands['transaction']['options'];

        $options = new stdClass();
        $options->exportCsv = $this->options['export-csv'] ?? $commandOptions['export-csv']['default'];
        $options->bank = $this->options['bank'] ?? null;
        $options->isin = $this->options['isin'] ?? null;
        $options->asset = $this->options['asset'] ?? null;
        $options->dateFrom = $this->options['date-from'] ?? null;
        $options->dateTo = $this->options['date-to'] ?? null;
        $options->currentHoldings = $this->options['current-holdings'] ?? $commandOptions['current-holdings']['default'];
        $options->cashFlow = $this->options['cash-flow'] ?? null;
        $options->account = $this->options['account'] ?? null;
        $options->displayLog = $this->options['display-log'] ?? $commandOptions['display-log']['default'];

        return $options;
    }

    public function execute(): void
    {
        $options = $this->getParsedOptions();

        $transactionLoader = new TransactionLoader(
            $options->bank,
            $options->isin,
            $options->asset,
            $options->dateFrom,
            $options->dateTo,
            $options->currentHoldings,
            $options->account
        );

        $transactions = $transactionLoader->getTransactions();
        $assets = $transactionLoader->getFinancialAssets($transactions);

        $this->generatePortfolio($assets, $transactions);

        if (!file_exists(ROOT_PATH . '/resources/tmp/historical_prices')) {
            mkdir(ROOT_PATH . '/resources/tmp/historical_prices', 0777, true);
        }

        $tickers = file_get_contents(ROOT_PATH . '/resources/tmp/tickers.json');
        $tickers = json_decode($tickers);

        foreach ($tickers as $tickerInfo) {
            if ($options->isin !== null && $options->isin !== $tickerInfo->isin) {
                continue;
            }

            if ($tickerInfo->ticker === null) {
                echo "No ticker for {$tickerInfo->name}" . PHP_EOL;
                continue;
            }

            $historicalPricesFile = ROOT_PATH . '/resources/tmp/historical_prices/' . $tickerInfo->isin . '.json';
            if (!file_exists($historicalPricesFile) || filesize($historicalPricesFile) === 0) {
                echo "Fetching historical prices for {$tickerInfo->name}" . PHP_EOL;
                $historicalPrices = $transactionLoader->getHistoricalPrices($tickerInfo->ticker, $transactions[0]->getDateString(), date('Y-m-d'));
                file_put_contents($historicalPricesFile, json_encode($historicalPrices, JSON_PRETTY_PRINT));
            } else {
                $existingHistoricalPrices = json_decode(file_get_contents($historicalPricesFile), true);
                if (empty($existingHistoricalPrices)) {
                    continue;
                }

                $lastDate = array_keys($existingHistoricalPrices)[count($existingHistoricalPrices) - 1];
                $dateFrom = new DateTime($lastDate);
                $dateFrom->modify('+1 day');

                if ($dateFrom > date_create() || $dateFrom->diff(date_create())->days > 30) {
                    continue;
                }

                echo "Updating historical prices for {$tickerInfo->name}" . PHP_EOL;

                $historicalPrices = $transactionLoader->getHistoricalPrices($tickerInfo->ticker, $dateFrom->format('Y-m-d'), date('Y-m-d'));

                $updatedHistoricalPrices = array_merge($existingHistoricalPrices, $historicalPrices);
                file_put_contents($historicalPricesFile, json_encode($updatedHistoricalPrices, JSON_PRETTY_PRINT));
            }
        }

        return;

        if ($options->cashFlow) {
            $assets = $transactionLoader->getFinancialAssets($transactions);

            $profitCalculator = new ProfitCalculator($options->currentHoldings);
            $result = $profitCalculator->calculate($assets, $transactionLoader->overview);

            foreach ($result->overview->cashFlows as $cashFlow) {
                $res = $cashFlow->getDateString() . ' | ';
                $res .= TextColorizer::colorText($cashFlow->getBankName(), 'grey') . ' | ';
                $res .= TextColorizer::colorText($cashFlow->account, 'green') . ' | ';
                $res .= TextColorizer::colorText($cashFlow->name, 'pink') . ' | ';
                $res .= TextColorizer::colorText($cashFlow->getTypeName(), 'yellow') . ' | ';
                $res .= TextColorizer::colorText($this->presenter->formatNumber($cashFlow->rawAmount), 'cyan');

                echo $res . PHP_EOL;
            }

            if ($options->exportCsv) {
                $cashFlowArray = [];
                foreach ($result->overview->cashFlows as $cashFlow) {
                    $amount = $cashFlow->rawAmount;
                    if ($cashFlow->getTypeName() === 'deposit') {
                        $amount = $amount * -1;
                    } elseif ($cashFlow->getTypeName() === 'withdrawal') {
                        $amount = abs($amount);
                    }
                    if (!in_array($cashFlow->type, [
                        TransactionType::DEPOSIT,
                        TransactionType::WITHDRAWAL,
                        TransactionType::DIVIDEND,
                        TransactionType::CURRENT_HOLDING,
                        TransactionType::FEE,
                        TransactionType::FOREIGN_WITHHOLDING_TAX,
                        TransactionType::RETURNED_FOREIGN_WITHHOLDING_TAX
                    ])) {
                        continue;
                    }
                    $cashFlowArray[] = [
                        $cashFlow->getDateString(),
                        $cashFlow->getBankName(),
                        $cashFlow->account,
                        $cashFlow->name,
                        $cashFlow->getTypeName(),
                        $amount
                    ];
                }
                $headers = ['Datum', 'Bank', 'Konto', 'Namn', 'Typ', 'Belopp'];
                Exporter::exportToCsv($headers, $cashFlowArray, 'cash_flow');
            }
        } else {
            echo 'Datum | Bank | Konto | Namn | Typ | Belopp | Antal | Pris' . PHP_EOL;
            foreach ($transactions as $transaction) {
                $res = $transaction->getDateString() . ' | ';
                $res .= TextColorizer::colorText($transaction->getBankName(), 'grey') . ' | ';
                $res .= TextColorizer::colorText($transaction->account, 'green') . ' | ';
                $res .= TextColorizer::colorText($transaction->name . " ({$transaction->isin})", 'pink') . ' | ';
                $res .= TextColorizer::colorText($transaction->getTypeName(), 'yellow') . ' | ';
                $res .= TextColorizer::colorText($this->presenter->formatNumber((float) $transaction->rawAmount), 'cyan') . ' | ';
                $res .= TextColorizer::colorText($this->presenter->formatNumber((float) $transaction->rawQuantity), 'grey') . ' | ';
                $res .= TextColorizer::backgroundColor($this->presenter->formatNumber((float) $transaction->rawPrice), 'green');

                echo $res . PHP_EOL;
            }
        }

        Logger::getInstance()->printInfos();

        if ($options->displayLog) {
            Logger::getInstance()
                ->printNotices()
                ->printWarnings();
        }
    }

    /**
     * @param FinancialAsset[] $assets
     * @param Transaction[] $transactions
     */
    public function generatePortfolio(array $assets, array $transactions): void
    {
        // $avanzaFiles = glob(IMPORT_DIR . '/banks/avanza/*.csv');
        // $nordnetFiles = glob(IMPORT_DIR . '/banks/nordnet/*.csv');
        $avanzaFile = Utility::getLatestModifiedFile(IMPORT_DIR . '/banks/avanza');
        $nordnetFile = Utility::getLatestModifiedFile(IMPORT_DIR . '/banks/nordnet');

        $files = [$avanzaFile, $nordnetFile];

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
    }
}
