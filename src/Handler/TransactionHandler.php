<?php

namespace Avk\Handler;

use Avk\DataStructure\Transaction;
use Avk\Enum\TransactionType;
use Avk\Service\FileManager\Exporter;
use Avk\Service\ProfitCalculator;
use Avk\Service\Transaction\TransactionLoader;
use Avk\Service\Utility;
use Avk\View\Presenter;
use Avk\View\TextColorizer;

class TransactionHandler
{
    public Presenter $presenter;
    public TransactionLoader $transactionLoader;

    /**
     * @var Transaction[]
     */
    public array $transactions;

    public function __construct(Presenter $presenter, TransactionLoader $transactionLoader)
    {
        $this->presenter = $presenter;
        $this->transactionLoader = $transactionLoader;
        $this->transactions = $transactionLoader->getTransactions();
    }

    public function getCashFlowDates(bool $currentHoldings): void
    {
        $assets = $this->transactionLoader->getFinancialAssets($this->transactions);

        $profitCalculator = new ProfitCalculator($currentHoldings);
        $result = $profitCalculator->calculate($assets, $this->transactionLoader->overview);

        $cashFlowArray = [];
        foreach ($result->overview->cashFlows as $cashFlow) {
            $amount = $cashFlow->rawAmount;
            if ($cashFlow->getTypeName() === 'deposit') {
                $amount *= -1;
            } elseif ($cashFlow->getTypeName() === 'withdrawal') {
                $amount = abs($amount);
            }
            if (!in_array($cashFlow->type, [
                TransactionType::DEPOSIT,
                TransactionType::WITHDRAWAL,
                TransactionType::DIVIDEND
            ])) {
                continue;
            }

            if (!isset($cashFlowArray[$cashFlow->getDateString()])) {
                $cashFlowArray[$cashFlow->getDateString()] = [$cashFlow->getDateString()];
            }
        }
        $headers = ['Datum'];
        Exporter::exportToCsv($headers, $cashFlowArray, 'cash_flow_dates');
    }

    public function displayTransactions(): void
    {
        echo 'Datum | Bank | Konto | Namn | Typ | Belopp | Antal | Pris' . PHP_EOL;
        foreach ($this->transactions as $transaction) {
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

    public function updatePortfolio(): void
    {
        $assets = $this->transactionLoader->getFinancialAssets($this->transactions);
        $this->transactionLoader->generatePortfolio($assets, $this->transactions);
    }

    public function getCashFlows(bool $currentHoldings, bool $exportCsv): void
    {
        $assets = $this->transactionLoader->getFinancialAssets($this->transactions);

        $profitCalculator = new ProfitCalculator($currentHoldings);
        $result = $profitCalculator->calculate($assets, $this->transactionLoader->overview);

        foreach ($result->overview->cashFlows as $cashFlow) {
            $res = $cashFlow->getDateString() . ' | ';
            $res .= TextColorizer::colorText($cashFlow->getBankName(), 'grey') . ' | ';
            $res .= TextColorizer::colorText($cashFlow->account, 'green') . ' | ';
            $res .= TextColorizer::colorText($cashFlow->name, 'pink') . ' | ';
            $res .= TextColorizer::colorText($cashFlow->getTypeName(), 'yellow') . ' | ';
            $res .= TextColorizer::colorText($this->presenter->formatNumber($cashFlow->rawAmount), 'cyan');

            echo $res . PHP_EOL;
        }

        if ($exportCsv) {
            $cashFlowArray = [];
            foreach ($result->overview->cashFlows as $cashFlow) {
                $amount = $cashFlow->rawAmount;
                if ($cashFlow->getTypeName() === 'deposit') {
                    $amount *= -1;
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
    }

    // TODO: This should be moved elsewhere
    public function updateHistoricalPrices(?string $filterIsin): void
    {
        if (!file_exists(ROOT_PATH . '/resources/tmp/historical_prices')) {
            mkdir(ROOT_PATH . '/resources/tmp/historical_prices', 0777, true);
        }

        $tickers = Utility::jsonDecodeFromFile(ROOT_PATH . '/resources/tmp/tickers.json');
        if (!is_array($tickers)) {
            return;
        }

        foreach ($tickers as $tickerInfo) {
            if ($filterIsin !== null && $filterIsin !== $tickerInfo->isin) {
                continue;
            }

            if ($tickerInfo->ticker === null) {
                echo "No ticker for {$tickerInfo->name}" . PHP_EOL;
                continue;
            }

            $historicalPricesFile = ROOT_PATH . '/resources/tmp/historical_prices/' . $tickerInfo->isin . '.json';
            if (!file_exists($historicalPricesFile) || filesize($historicalPricesFile) === 0) {
                echo "Fetching historical prices for {$tickerInfo->name}" . PHP_EOL;

                $historicalPrices = $this->transactionLoader->getHistoricalPrices(
                    $tickerInfo->ticker,
                    $this->transactions[0]->getDateString(),
                    date('Y-m-d')
                );

                file_put_contents($historicalPricesFile, json_encode($historicalPrices, JSON_PRETTY_PRINT));
            } else {
                $existingHistoricalPrices = file_get_contents($historicalPricesFile);
                if (!$existingHistoricalPrices) {
                    continue;
                }
                $existingHistoricalPrices = json_decode($existingHistoricalPrices, true);

                $lastDate = array_keys($existingHistoricalPrices)[count($existingHistoricalPrices) - 1] ?? null;
                if (!$lastDate || !is_string($lastDate)) {
                    continue;
                }
                $dateFrom = new \DateTime($lastDate);
                $dateFrom->modify('+1 day');

                if ($dateFrom > date_create() || $dateFrom->diff(date_create())->days > 30) { // @phpstan-ignore-line
                    continue;
                }

                echo "Updating historical prices for {$tickerInfo->name}" . PHP_EOL;

                $historicalPrices = $this->transactionLoader->getHistoricalPrices($tickerInfo->ticker, $dateFrom->format('Y-m-d'), date('Y-m-d'));

                $updatedHistoricalPrices = array_merge($existingHistoricalPrices, $historicalPrices);
                file_put_contents($historicalPricesFile, json_encode($updatedHistoricalPrices, JSON_PRETTY_PRINT));
            }
        }
    }
}
