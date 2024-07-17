<?php declare(strict_types=1);

namespace Avk\Command;

use Avk\DataStructure\Portfolio;
use Avk\Enum\CommandOptionName;
use Avk\Enum\TransactionType;
use Avk\Service\Performance\TimeWeightedReturn;
use Avk\Service\ProfitCalculator;
use Avk\Service\Transaction\TransactionLoader;
use Avk\Service\Transaction\TransactionMapper;
use Avk\View\Logger;
use Avk\View\TextColorizer;

class CalculateProfitCommand extends CommandBase
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

        $portfolio = $transactionLoader->getPortfolio();
        if (!$portfolio) {
            $transactions = $transactionLoader->getTransactions();
            $assets = $transactionLoader->getFinancialAssets($transactions);

            $transactionLoader->generatePortfolio($assets, $transactions);

            $portfolio = $transactionLoader->getPortfolio();

            if (!$portfolio) {
                Logger::getInstance()->addWarning('Portfölj saknas för att kunna utföra beräkningar.');
                return;
            }
        }

        $transactionMapper = new TransactionMapper($transactionLoader->overview);

        if ($this->command->getOption(CommandOptionName::TWR)->value) {
            $this->calculateTwr($transactionMapper, $portfolio);
        } else {
            $assets = [];
            foreach ($portfolio->portfolioTransactions as $row) {
                $transactions = $transactionLoader->filterTransactions($row->transactions);

                if (count($transactions) === 0) {
                    continue;
                }

                $asset = $transactionMapper->addTransactionsToAssetByIsin($row->isin, $row->name, $transactions);
                $assets[] = $asset;
            }

            $transactions = $transactionLoader->filterTransactions($portfolio->accountTransactions);
            if ($transactions) {
                foreach ($transactions as $transaction) {
                    $transactionMapper->handleNonAssetTransactionType($transaction);
                }
            }

            $profitCalculator = new ProfitCalculator($this->command->getOption(CommandOptionName::CURRENT_HOLDINGS)->value);
            $result = $profitCalculator->calculate($assets, $transactionLoader->overview);

            if ($this->command->getOption(CommandOptionName::VERBOSE)->value) {
                $this->presenter->displayDetailedAssets($result->assets);
            } else {
                $this->presenter->generateAssetTable($result->overview, $result->assets);
            }

            if (!empty($result->overview->currentHoldingsWeighting)) {
                $weightings = array_values($result->overview->currentHoldingsWeighting);

                if (!empty($weightings)) {
                    echo PHP_EOL . TextColorizer::backgroundColor('Portföljviktning:', 'pink', 'black') . PHP_EOL. PHP_EOL;

                    $maxValue = max($weightings);
                    foreach ($result->overview->currentHoldingsWeighting as $isin => $weight) {
                        $this->presenter->printRelativeProgressBarPercentage($isin, $weight, $maxValue);
                    }
                }
            }

            $dividendPeriods = [];
            foreach ($result->overview->cashFlows as $cashFlow) {
                if ($cashFlow->type === TransactionType::DIVIDEND) {
                    if (!isset($dividendPeriods[$cashFlow->date->format('Y')])) {
                        $dividendPeriods[$cashFlow->date->format('Y')] = 0;
                    }
                    $dividendPeriods[$cashFlow->date->format('Y')] += $cashFlow->rawAmount;
                }
            }

            if ($dividendPeriods) {
                echo PHP_EOL . TextColorizer::backgroundColor('Utdelningar:', 'pink', 'black') . PHP_EOL . PHP_EOL;

                $dividends = array_values($dividendPeriods);
                $maxValue = max($dividends);
                foreach ($dividendPeriods as $date => $dividend) {
                    $this->presenter->printRelativeProgressBarAmount((string) $date, $dividend, $maxValue);
                }
            }

            if ($this->command->getOption(CommandOptionName::OVERVIEW)->value) {
                $this->presenter->displayInvestmentReport($result->overview);
            }

            foreach ($result->currentHoldingsMissingPricePerShare as $companyMissingPrice) {
                Logger::getInstance()->addInfo('Kurspris saknas för ' . $companyMissingPrice);
            }

            $this->presenter->displayAssetNotices($result->assets);
        }

        Logger::getInstance()->printInfos();

        if ($this->command->getOption(CommandOptionName::DISPLAY_LOG)->value) {
            Logger::getInstance()
                ->printNotices()
                ->printWarnings();
        }
    }

    private function calculateTwr(TransactionMapper $transactionMapper, Portfolio $portfolio): void
    {
        $twr = new TimeWeightedReturn($transactionMapper);
        $twrResult = $twr->calculate(
            $portfolio,
            // $options->dateFrom,
            $this->command->getOption(CommandOptionName::DATE_FROM)->value,
            $this->command->getOption(CommandOptionName::DATE_TO)->value,
            $this->command->getOption(CommandOptionName::BANK)->value
        );

        echo PHP_EOL;

        $totalValues = array_values($twrResult->totalValues);

        if ($totalValues) {
            $maxValue = max($totalValues);
            foreach ($twrResult->totalValues as $date => $totalValue) {
                $this->presenter->printRelativeProgressBarAmount($date, $totalValue, $maxValue);
            }
        }

        echo "\nSubperiod Returns:\n";
        foreach ($twrResult->returns as $index => $row) {
            echo 'Subperiod ' . ($index + 1) . ' (' . $row['endDate'] . '): ' . ($row['return'] * 100) . "%\n";
        }

        echo PHP_EOL . 'Total TWR: ' . ($twrResult->twr * 100) . '%' . PHP_EOL;
    }
}
