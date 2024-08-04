<?php declare(strict_types=1);

namespace Avk\Handler;

use Avk\DataStructure\Portfolio;
use Avk\Enum\TransactionType;
use Avk\Service\Performance\TimeWeightedReturn;
use Avk\Service\Performance\ProfitCalculator;
use Avk\Service\Transaction\TransactionLoader;
use Avk\Service\Transaction\TransactionMapper;
use Avk\View\Logger;
use Avk\View\Presenter;
use Avk\View\TextColorizer;

class CalculateProfitHandler
{
    public Presenter $presenter;
    public Portfolio $portfolio;
    public TransactionLoader $transactionLoader;
    public TransactionMapper $transactionMapper;

    public function __construct(Presenter $presenter, TransactionLoader $transactionLoader)
    {
        $this->presenter = $presenter;
        $this->transactionLoader = $transactionLoader;
        $this->transactionMapper = new TransactionMapper($transactionLoader->overview);

        $portfolio = $this->getPortfolio();
        if (!$portfolio) {
            Logger::getInstance()->printMessage('Portfölj saknas för att kunna utföra beräkningar.');
            throw new \Exception('Portfolio is missing');
        }

        $this->portfolio = $portfolio;
    }

    public function displayPerformance(bool $currentHoldings, bool $verbose, bool $displayOverview): void
    {
        $assets = [];
        foreach ($this->portfolio->portfolioTransactions as $row) {
            $transactions = $this->transactionLoader->filterTransactions($row->transactions);

            if (count($transactions) === 0) {
                continue;
            }

            $asset = $this->transactionMapper->addTransactionsToAssetByIsin($row->isin, $row->name, $transactions);
            $assets[] = $asset;
        }

        $transactions = $this->transactionLoader->filterTransactions($this->portfolio->accountTransactions);
        if ($transactions) {
            foreach ($transactions as $transaction) {
                $this->transactionMapper->handleNonAssetTransactionType($transaction);
            }
        }

        $profitCalculator = new ProfitCalculator($currentHoldings);
        $result = $profitCalculator->calculate($assets, $this->transactionLoader->overview);

        if ($verbose) {
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

        if ($displayOverview) {
            $this->presenter->displayInvestmentReport($result->overview);
        }

        foreach ($result->currentHoldingsMissingPricePerShare as $companyMissingPrice) {
            Logger::getInstance()->addInfo('Kurspris saknas för ' . $companyMissingPrice);
        }

        $this->presenter->displayAssetNotices($result->assets);
    }

    public function calculateTwr(?string $filterDateFrom, ?string $filterDateTo, ?string $filterBank): void
    {
        $twr = new TimeWeightedReturn($this->transactionMapper);
        $twrResult = $twr->calculate(
            $this->portfolio,
            // $options->dateFrom,
            $filterDateFrom,
            $filterDateTo,
            $filterBank
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

    private function getPortfolio(): ?Portfolio
    {
        $portfolio = $this->transactionLoader->getPortfolio();
        if (!$portfolio) {
            $transactions = $this->transactionLoader->getTransactions();
            $assets = $this->transactionLoader->getFinancialAssets($transactions);

            $this->transactionLoader->generatePortfolio($assets, $transactions);
            $portfolio = $this->transactionLoader->getPortfolio();

            if (!$portfolio) {
                return null;
            }
        }

        return $portfolio;
    }
}
