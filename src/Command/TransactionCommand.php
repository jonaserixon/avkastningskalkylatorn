<?php declare(strict_types=1);

namespace Avk\Command;

use Avk\Enum\CommandOptionName;
use Avk\Enum\TransactionType;
use Avk\Service\FileManager\Exporter;
use Avk\Service\ProfitCalculator;
use Avk\Service\Transaction\TransactionLoader;
use Avk\View\Logger;
use Avk\View\TextColorizer;

class TransactionCommand extends CommandBase
{
    public function execute(): void
    {
        $transactionLoader = new TransactionLoader(
            $this->command->getOption(CommandOptionName::BANK)->value,
            $this->command->getOption(CommandOptionName::ISIN)->value,
            $this->command->getOption(CommandOptionName::ASSET)->value,
            $this->command->getOption(CommandOptionName::DATE_FROM)->value,
            $this->command->getOption(CommandOptionName::DATE_TO)->value,
            $this->command->getOption(CommandOptionName::CURRENT_HOLDINGS)->value,
            $this->command->getOption(CommandOptionName::ACCOUNT)->value
        );

        $transactions = $transactionLoader->getTransactions();
        $assets = $transactionLoader->getFinancialAssets($transactions);

        $transactionLoader->generatePortfolio($assets, $transactions);

        /*
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
        */

        if ($this->command->getOption(CommandOptionName::CASH_FLOW)->value) {
            $assets = $transactionLoader->getFinancialAssets($transactions);

            $profitCalculator = new ProfitCalculator($this->command->getOption(CommandOptionName::CURRENT_HOLDINGS)->value);
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

            if ($this->command->getOption(CommandOptionName::EXPORT_CSV)->value) {
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

        if ($this->command->getOption(CommandOptionName::DISPLAY_LOG)->value) {
            Logger::getInstance()
                ->printNotices()
                ->printWarnings();
        }
    }
}
