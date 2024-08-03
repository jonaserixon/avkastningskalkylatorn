<?php

namespace Avk\Service\Performance;

use Avk\DataStructure\FinancialAsset;
use Avk\DataStructure\Portfolio;
use Avk\DataStructure\Transaction;
use Avk\Enum\TransactionType;
use Avk\Service\API\Frankfurter\FrankfurterWrapper;
use Avk\Service\Transaction\TransactionMapper;
use Avk\Service\Utility;
use Avk\View\Logger;
use DateTime;
use Exception;
use stdClass;

class TimeWeightedReturn
{
    private TransactionMapper $transactionMapper;

    public function __construct(TransactionMapper $transactionMapper)
    {
        $this->transactionMapper = $transactionMapper;
    }

    public function calculate(Portfolio $portfolio, ?string $filterDateFrom = null, ?string $filterDateTo = null, ?string $filterBank = null): stdClass
    {
        echo 'Calculating TWR...' . PHP_EOL;

        $subPeriods = $this->createSubPeriods($portfolio, $filterDateFrom, $filterDateTo, $filterBank);
        $subPeriodDates = $subPeriods->subPeriodDates;
        $accountTransactions = $subPeriods->accountTransactions;
        $portfolioTransactions = $subPeriods->portfolioTransactions;

        $tickers = Utility::jsonDecodeFromFile(ROOT_PATH . '/resources/tmp/tickers.json');
        if (!is_array($tickers)) {
            throw new Exception('Failed to decode tickers.json');
        }

        $historicalCurrencyExchangeRates = $this->getHistoricalExchangeRates(ROOT_PATH . '/resources/tmp/historical_currency.csv');

        $previousEndValue = 0;
        $index = 0;
        $twr = 1;
        $assets = [];
        $historicalPricesCache = [];
        $returns = [];
        $totalValues = [];
        foreach ($subPeriodDates as $subPeriod) {
            $startDateString = $subPeriod[0];
            $endDateString = $subPeriod[1];
            $startDate = new DateTime($subPeriod[0]);
            $endDate = new DateTime($subPeriod[1]);
            $dividendSum = 0;
            $startValue = $previousEndValue;

            $accountTransactionsInSubPeriod = $this->filterAndSortTransactions($accountTransactions, $startDate, $endDate);
            $portfolioTransactionsInSubPeriod = $this->filterAndSortTransactions($portfolioTransactions, $startDate, $endDate);

            // PORTFOLIO TRANSACTIONS
            foreach ($portfolioTransactionsInSubPeriod as $transaction) {
                if ($transaction->isin === null) { // This should not be able to happen.
                    throw new Exception('ISIN is missing for ' . $transaction->name);
                }

                if (!isset($assets[$transaction->isin])) {
                    $assets[$transaction->isin] = new FinancialAsset($transaction->name, $transaction->isin);
                }

                $this->transactionMapper->addTransactionsToExistingAsset($assets[$transaction->isin], $transaction);
                if ($transaction->type === TransactionType::DIVIDEND) {
                    $dividendSum += $transaction->rawAmount;
                }
            }

            // ACCOUNT TRANSACTIONS
            $netCashFlow = 0;
            foreach ($accountTransactionsInSubPeriod as $transaction) {
                if ($index === 0 && $transaction->date == $startDate) {
                    $startValue += $transaction->rawAmount;
                }
                $this->transactionMapper->handleNonAssetTransactionType($transaction);

                $netCashFlow += $transaction->rawAmount;
            }

            $portfolioValue = 0;
            foreach ($assets as $isin => $asset) {
                if ($asset->getCurrentNumberOfShares() <= 0) {
                    continue;
                }

                $fileName = ROOT_PATH . '/resources/tmp/historical_prices/' . $isin . '.json';

                // Cache historical prices
                if (!isset($historicalPricesCache[$isin])) {
                    if (file_exists($fileName)) {
                        $tmpHistoricalPrices = file_get_contents($fileName);
                        $tmpHistoricalPrices = $tmpHistoricalPrices ? json_decode($tmpHistoricalPrices, true) : [];
                        $historicalPricesCache[$isin] = $tmpHistoricalPrices;
                    } else {
                        $historicalPricesCache[$isin] = [];
                    }
                }
                $historicalPrices = $historicalPricesCache[$isin];

                $sharePrice = $this->getSharePrice($asset, $historicalPrices, $endDateString);

                $exchangeRate = $this->getExchangeRate($tickers, $isin, $historicalCurrencyExchangeRates, $endDateString);
                $sharePrice *= $exchangeRate;

                // echo 'Asset ' . $asset->name . ' (' . $asset->isin . ') has a price of ' . $sharePrice . ' on ' . $endDateString . ', num. of shares: ' . $asset->getCurrentNumberOfShares() . ', total value of ' . $sharePrice * $asset->getCurrentNumberOfShares() . PHP_EOL;

                $portfolioValue += $sharePrice * $asset->getCurrentNumberOfShares();
            }

            usort($this->transactionMapper->overview->cashFlows, function (Transaction $a, Transaction $b): int {
                return strtotime($a->getDateString()) <=> strtotime($b->getDateString());
            });
            $cashBalance = $this->transactionMapper->overview->calculateBalance($this->transactionMapper->overview->cashFlows);

            $endValue = $portfolioValue + $cashBalance + $dividendSum;
            $previousEndValue = $endValue;

            if ($index === 0) {
                $startValue = $netCashFlow;
                $return = ($endValue - $startValue) / $startValue;
            } else {
                $return = ($endValue - $startValue - $netCashFlow) / $startValue;
            }

            $twr *= (1 + $return);
            $returns[] = [
                'endDate' => $endDateString,
                'return' => $return
            ];

            $totalValues[$endDateString] = $endValue;

            /*
            print_r([
                'startDate' => $startDateString,
                'endDate' => $endDateString,
                'startValue' => $startValue,
                'endValue' => $endValue,
                'portfolioValue' => $portfolioValue,
                'netCashFlow' => $netCashFlow,
                'cashBalance' => $cashBalance,
                'dividendSum' => $dividendSum,
                'return' => $return,
                'TWR' => $twr
            ]);
            */

            $index++;

            echo "\r";
            echo "Processing sub-period " . $index . ' / ' . count($subPeriodDates);
        }

        $twr -= 1;

        $result = new stdClass();
        $result->twr = $twr;
        $result->returns = $returns;
        $result->totalValues = $totalValues;

        return $result;
    }

    private function createSubPeriods(Portfolio $portfolio, ?string $filterDateFrom, ?string $filterDateTo, ?string $filterBank): stdClass
    {
        $subPeriodDates = [];
        $subPeriodIndex = 0;
        $previousDate = null;

        $dateFrom = $filterDateFrom ? new DateTime($filterDateFrom) : null;
        $dateTo = $filterDateTo ? new DateTime($filterDateTo) : null;

        $cashFlowTransactions = [];
        foreach ($portfolio->accountTransactions as $index => $transaction) {
            if ($filterBank && mb_strtoupper($filterBank) !== $transaction->bank->value) {
                continue;
            }

            if (!in_array($transaction->type, [TransactionType::DEPOSIT, TransactionType::WITHDRAWAL])) {
                continue;
            }
            if (($dateFrom !== null && $transaction->date < $dateFrom) ||
                ($dateTo !== null && $transaction->date > $dateTo)
            ) {
                continue;
            }

            // The first period should start with a transaction amount of more than x kr.
            if ($index > 0 || ($index < 1 && $transaction->rawAmount > 100)) {
                // Initialize a new subperiod.
                if (!isset($subPeriodDates[$subPeriodIndex])) {
                    $subPeriodDates[$subPeriodIndex] = [];
                }

                // If the current subperiod has two dates, move to the next subperiod.
                if (count($subPeriodDates[$subPeriodIndex]) === 2) {
                    $previousDate = $subPeriodDates[$subPeriodIndex][1];

                    $subPeriodIndex++;
                    $subPeriodDates[$subPeriodIndex][] = $previousDate;
                }

                // Add the date to the current subperiod.
                if (!in_array($transaction->getDateString(), $subPeriodDates[$subPeriodIndex])) {
                    $subPeriodDates[$subPeriodIndex][] = $transaction->getDateString();
                }
            }
            

            $cashFlowTransactions[] = $transaction;
        }

        $portfolioTransactions = [];
        foreach ($portfolio->portfolioTransactions as $portfolioTransaction) {
            foreach ($portfolioTransaction->transactions as $transaction) {
                if ($filterBank && mb_strtoupper($filterBank) !== $transaction->bank->value) {
                    continue;
                }

                $portfolioTransactions[] = $transaction;
            }
        }

        $lastSubPeriod = end($subPeriodDates);
        if (isset($lastSubPeriod[1])) {
            $lastDate = $lastSubPeriod[1];

            $currentSubPeriod = [
                $lastDate,
                date('Y-m-d')
            ];
            $subPeriodDates[] = $currentSubPeriod;
        } else {
            // If the last subperiod does not have an end date.
            $subPeriodDates[count($subPeriodDates) - 1][] = date('Y-m-d');
        }

        $result = new stdClass();
        $result->subPeriodDates = $subPeriodDates;
        $result->accountTransactions = $cashFlowTransactions;
        $result->portfolioTransactions = $portfolioTransactions;

        return $result;
    }

    /**
     * @param stdClass[] $tickers
     * @param mixed[] $historicalCurrencyExchangeRates
     */
    private function getExchangeRate(array $tickers, string $isin, array $historicalCurrencyExchangeRates, string $endDateString): float|int
    {
        $exchangeRate = 1;

        $currency = null;
        foreach ($tickers as $tickerInfo) {
            if ($tickerInfo->isin === $isin) {
                $currency = $tickerInfo->currency;
                break;
            }
        }

        if ($currency === null) {
            throw new Exception('Currency not found for ' . $isin);
        }

        if ($currency !== 'SEK') {
            $exchangeRateNotFound = true;
            foreach ($historicalCurrencyExchangeRates as $exchangeRateRow) {
                if ($exchangeRateRow['Date'] === $endDateString) {
                    $exchangeRate = $exchangeRateRow[$currency];
                    $exchangeRateNotFound = false;
                    break;
                }
            }

            if ($exchangeRateNotFound) {
                // TODO: add the result to the historical_currency.csv file
                $frankfurter = new FrankfurterWrapper();
                $exchangeRate = $frankfurter->getExchangeRateByCurrencyAndDate($currency, $endDateString);

                Logger::getInstance()->printMessage('Fetching exchange rate for ' . $currency . ' on ' . $endDateString . ' from Frankfurter API');
                // throw new Exception('Exchange rate not found for ' . $currency . ' on ' . $endDateString);
            }
        }

        return $exchangeRate;
    }

    /**
     * @param Transaction[] $transactions
     * @return Transaction[]
     */
    private function filterAndSortTransactions(array $transactions, DateTime $startDate, DateTime $endDate): array
    {
        $subPeriodTransactions = array_filter($transactions, function (Transaction $transaction) use ($startDate, $endDate): bool {
            return $transaction->date >= $startDate && $transaction->date < $endDate;
        });

        $subPeriodTransactions = array_values($subPeriodTransactions);
        usort($subPeriodTransactions, function (Transaction $a, Transaction $b): int {
            return strtotime($a->getDateString()) <=> strtotime($b->getDateString());
        });

        return $subPeriodTransactions;
    }

    /**
     * @param mixed[] $historicalPrices
     */
    private function getSharePrice(FinancialAsset $asset, array $historicalPrices, string $endDateString): float
    {
        $sharePrice = 0;

        // Handle case when there are no historical prices. Use the last known price instead.
        if (empty($historicalPrices)) {
            $assetTransactions = $asset->getTransactions();
            $lastTransaction = null;
            foreach (array_reverse($assetTransactions) as $transaction) {
                if (in_array($transaction->type, [TransactionType::BUY, TransactionType::SELL])) {
                    $lastTransaction = $transaction;
                    break;
                }
            }

            if ($lastTransaction === null) {
                throw new Exception('Missing price for ' . $asset->name . ' (' . $asset->isin . ') on ' . $endDateString);
            }

            $sharePrice = abs(floatval($lastTransaction->rawAmount)) / $lastTransaction->rawQuantity;

            Logger::getInstance()->addNotice('Missing price for: ' . $asset->name . ' (' . $asset->isin . ')' . ' on ' . $endDateString . ', using last known price ' . $lastTransaction->getDateString() . ' (' . $sharePrice . ')');
        } else {
            if (isset($historicalPrices[$endDateString])) {
                $sharePrice = $historicalPrices[$endDateString];
            } else {
                foreach ($historicalPrices as $date => $historicalPrice) {
                    if ($date > $endDateString) {
                        $sharePrice = $historicalPrice;
                        break;
                    }
                }

                // Handle cases when there are no historical prices for the end date.
                if ($sharePrice === 0) {
                    $tmpHistoricalPrices = $historicalPrices;
                    $sharePrice = end($tmpHistoricalPrices);
                }
            }
        }

        return $sharePrice;
    }

    /**
     * @return mixed[]
     */
    private function getHistoricalExchangeRates(string $filename): array
    {
        $exchangeRates = [];
        if (($handle = fopen($filename, "r")) !== false) {
            $headers = fgetcsv($handle, 1000, ",");
            if ($headers === false) {
                throw new Exception('Failed to read headers from ' . $filename);
            }

            while (($data = fgetcsv($handle, null, ",")) !== false) {
                $row = [];
                foreach ($headers as $key => $header) {
                    $row[$header] = $data[$key];
                }

                $exchangeRates[] = $row;
            }

            fclose($handle);
        }

        return $exchangeRates;
    }
}
