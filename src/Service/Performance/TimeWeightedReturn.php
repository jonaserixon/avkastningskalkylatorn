<?php

namespace src\Service\Performance;

use DateTime;
use Exception;
use src\DataStructure\FinancialAsset;
use src\DataStructure\Transaction;
use src\Enum\Bank;
use src\Enum\TransactionType;
use src\Service\Transaction\TransactionMapper;
use src\View\Logger;
use stdClass;

class TimeWeightedReturn
{
    private TransactionMapper $transactionMapper;

    public function __construct(TransactionMapper $transactionMapper)
    {
        $this->transactionMapper = $transactionMapper;
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

    public function calculate(stdClass $portfolio, ?string $filterDateFrom = null, ?string $filterDateTo = null): void
    {
        echo 'Calculating TWR...' . PHP_EOL;

        $subPeriods = $this->createSubPeriods($portfolio, $filterDateFrom, $filterDateTo);
        $subPeriodDates = $subPeriods->subPeriodDates;
        $accountTransactions = $subPeriods->accountTransactions;
        $portfolioTransactions = $subPeriods->portfolioTransactions;

        $tickers = file_get_contents(ROOT_PATH . '/resources/tmp/tickers.json');
        if ($tickers === false) {
            throw new Exception('Failed to read tickers.json');
        }
        $tickers = json_decode($tickers);

        $historicalCurrencyExchangeRateFile = ROOT_PATH . '/resources/tmp/historical_currency.csv';
        $historicalCurrencyExchangeRates = $this->getHistoricalExchangeRates($historicalCurrencyExchangeRateFile);

        $previousEndValue = 0;
        $index = 0;
        $twr = 1;
        $assets = [];
        $historicalPricesCache = [];
        $returns = [];
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
                if (!isset($assets[$transaction->isin])) {
                    $newAsset = new FinancialAsset();
                    $newAsset->name = $transaction->name;
                    $newAsset->isin = $transaction->isin;

                    $assets[$transaction->isin] = $newAsset;
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

                $sharePrice = 0;
                // Handle case when there are no historical prices. Use the last known price instead.
                if (empty($historicalPrices)) { 
                    $assetTransactions = $asset->getTransactions();
                    $lastTransaction = null;
                    foreach (array_reverse($assetTransactions) as $transaction) {
                        if (in_array($transaction->type, [TransactionType::BUY, TransactionType::SELL])){
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

                /*
                $tickerIndex = array_search($asset->isin, array_column($tickers, 'isin'));
                if ($tickerIndex === false) {
                    throw new Exception('Currency not found for ' . $asset->isin);
                }
                $currency = $tickers[$tickerIndex]->currency;
                */

                $currency = null;
                foreach ($tickers as $tickerInfo) {
                    if ($tickerInfo->isin === $asset->isin) {
                        $currency = $tickerInfo->currency;
                        break;
                    }
                }

                if ($currency === null) {
                    throw new Exception('Currency not found for ' . $asset->isin);
                }

                if ($currency !== 'SEK') {
                    $exchangeRateNotFound = true;
                    foreach ($historicalCurrencyExchangeRates as $exchangeRateRow) {
                        if ($exchangeRateRow['Date'] === $endDateString) {
                            $sharePrice *= $exchangeRateRow[$currency];
                            $exchangeRateNotFound = false;
                            break;
                        }
                    }

                    if ($exchangeRateNotFound) {
                        throw new Exception('Exchange rate not found for ' . $currency . ' on ' . $endDateString);
                    }
                }

                // echo 'Asset ' . $asset->name . ' (' . $asset->isin . ') has a price of ' . $sharePrice . ' on ' . $endDateString . ', num. of shares: ' . $asset->getCurrentNumberOfShares() . ', total value of ' . $sharePrice * $asset->getCurrentNumberOfShares() . PHP_EOL;

                $portfolioValue += $sharePrice * $asset->getCurrentNumberOfShares();
            }

            usort($this->transactionMapper->overview->cashFlows, function (Transaction $a, Transaction $b): int {
                return strtotime($a->getDateString()) <=> strtotime($b->getDateString());
            });
            $cashBalance = $this->transactionMapper->overview->calculateBalance($this->transactionMapper->overview->cashFlows);

            $index++;

            $endValue = $portfolioValue + $cashBalance + $dividendSum;
            $previousEndValue = $endValue;

            if ($index === 1) {
                $startValue = $netCashFlow;
                $return = ($previousEndValue - $startValue) / $startValue;
            } else {
                $return = ($endValue - $startValue - $netCashFlow) / $startValue;
            }

            $twr *= (1 + $return);
            $returns[] = $return;
            
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

            // echo "\r\033[K";
            // echo "Processing sub-period " . $index . ' / ' . count($subPeriodDates);
        }

        $twr -= 1;

        echo "\nSubperiod Returns:\n";
        foreach ($returns as $index => $return) {
            echo 'Subperiod ' . ($index + 1) . ': ' . ($return * 100) . "%\n";
        }

        echo 'Total TWR: ' . ($twr * 100) . '%';
    }
    
    private function createSubPeriods(stdClass $portfolio, ?string $filterDateFrom, ?string $filterDateTo): stdClass
    {
        $subPeriodDates = [];
        $subPeriodIndex = 0;
        $previousDate = null;

        $dateFrom = $filterDateFrom ? new DateTime($filterDateFrom) : null;
        $dateTo = $filterDateTo ? new DateTime($filterDateTo) : null;

        $cashFlowTransactions = [];
        foreach ($portfolio->accountTransactions as $row) {
            $transactionType = TransactionType::from($row->type);

            if (!in_array($transactionType, [TransactionType::DEPOSIT, TransactionType::WITHDRAWAL])) {
                continue;
            }

            if (($dateFrom !== null && new DateTime($row->date->date) < $dateFrom) || ($dateTo !== null && new DateTime($row->date->date) > $dateTo)){
                continue;
            }

            $transaction = new Transaction(
                new DateTime($row->date->date),
                Bank::from($row->bank),
                $row->account,
                $transactionType,
                $row->name,
                $row->description,
                $row->rawQuantity,
                $row->rawPrice,
                $row->pricePerShareSEK,
                $row->rawAmount,
                $row->commission,
                $row->currency,
                $row->isin,
                $row->exchangeRate
            );

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

            $cashFlowTransactions[] = $transaction;
        }

        $portfolioTransactions = [];
        foreach ($portfolio->portfolioTransactions as $row) {
            foreach ($row->transactions as &$transactionRow) {
                $transaction = new Transaction(
                    new DateTime($transactionRow->date->date),
                    Bank::from($transactionRow->bank),
                    $transactionRow->account,
                    TransactionType::from($transactionRow->type),
                    $transactionRow->name,
                    $transactionRow->description,
                    $transactionRow->rawQuantity,
                    $transactionRow->rawPrice,
                    $transactionRow->pricePerShareSEK,
                    $transactionRow->rawAmount,
                    $transactionRow->commission,
                    $transactionRow->currency,
                    $transactionRow->isin,
                    $transactionRow->exchangeRate
                );

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
