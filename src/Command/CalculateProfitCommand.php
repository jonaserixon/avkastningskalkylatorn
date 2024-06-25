<?php declare(strict_types=1);

namespace src\Command;

use DateTime;
use Exception;
use src\Command\CommandProcessor;
use src\DataStructure\FinancialAsset;
use src\DataStructure\Transaction;
use src\Enum\Bank;
use src\Enum\TransactionType;
use src\Service\ProfitCalculator;
use src\Service\Transaction\TransactionLoader;
use src\Service\Transaction\TransactionMapper;
use src\Service\Utility;
use src\View\Logger;
use src\View\TextColorizer;
use stdClass;

class CalculateProfitCommand extends CommandProcessor
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
        $commandOptions = $this->commands['calculate']['options'];

        $options = new stdClass();
        $options->verbose = $this->options['verbose'] ?? $commandOptions['verbose']['default'];
        // $options->exportCsv = $this->options['export-csv'] ?? $commandOptions['export-csv']['default'];
        $options->bank = $this->options['bank'] ?? null;
        $options->isin = $this->options['isin'] ?? null;
        $options->asset = $this->options['asset'] ?? null;
        $options->dateFrom = $this->options['date-from'] ?? null;
        $options->dateTo = $this->options['date-to'] ?? null;
        $options->currentHoldings = $this->options['current-holdings'] ?? $commandOptions['current-holdings']['default'];
        $options->overview = $this->options['overview'] ?? $commandOptions['overview']['default'];
        $options->account = $this->options['account'] ?? null;
        $options->displayLog = $this->options['display-log'] ?? $commandOptions['display-log']['default'];
        $options->TWR = $this->options['twr'] ?? null;

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

        $transactionLoader->overview->firstTransactionDate = '';
        $transactionLoader->overview->lastTransactionDate = '';
        $transactionMapper = new TransactionMapper($transactionLoader->overview);

        $portfolioFile = Utility::getLatestModifiedFile(ROOT_PATH . '/resources/portfolio', 'json');
        if ($portfolioFile === null) {
            throw new Exception('No portfolio file found.');
        }

        $portfolio = file_get_contents($portfolioFile);
        if ($portfolio === false) {
            throw new Exception('Failed to read portfolio file.');
        }

        $portfolio = json_decode($portfolio);

        if ($options->TWR) {
            $this->calculateTWR($portfolio, $transactionMapper, $options->dateFrom, $options->dateTo);
        } else {
            $assets = [];
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
    
                    $transactionRow = $transaction;
                }
                $transactions = $transactionLoader->filterTransactions($row->transactions);
                $asset = $transactionMapper->_addTransactionsToAsset($row->isin, $row->name, $transactions);
                $assets[] = $asset;
            }
    
            foreach ($portfolio->accountTransactions as $row) {
                $transaction = new Transaction(
                    new DateTime($row->date->date),
                    Bank::from($row->bank),
                    $row->account,
                    TransactionType::from($row->type),
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
                $transactions = $transactionLoader->filterTransactions([$transaction]);
    
                if (empty($transactions)) {
                    continue;
                }
    
                $transactionMapper->handleNonAssetTransactionType($transactions[0]);
            }
            
            /*
            $assets = $transactionLoader->getFinancialAssets($transactionLoader->getTransactions());
            */
    
            $profitCalculator = new ProfitCalculator($options->currentHoldings);
            $result = $profitCalculator->calculate($assets, $transactionLoader->overview);
    
            if ($options->verbose) {
                $this->presenter->displayDetailedAssets($result->assets);
            } else {
                $this->presenter->generateAssetTable($result->overview, $result->assets);
            }
    
            // $this->presenter->displayFinancialOverview($result->overview);
    
            if (!empty($result->overview->currentHoldingsWeighting)) {
                $weightings = array_values($result->overview->currentHoldingsWeighting);
    
                if (!empty($weightings)) {
                    echo PHP_EOL . TextColorizer::backgroundColor('Portföljviktning: ', 'pink', 'black') . PHP_EOL. PHP_EOL;
    
                    $maxValue = max($weightings);
                    foreach ($result->overview->currentHoldingsWeighting as $isin => $weight) {
                        $this->presenter->printRelativeProgressBar($isin, $weight, $maxValue);
                    }
                }
            }
    
            if ($options->overview) {
                $this->presenter->displayInvestmentReport($result->overview, $result->assets);
            }
    
            foreach ($result->currentHoldingsMissingPricePerShare as $companyMissingPrice) {
                Logger::getInstance()->addInfo('Kurspris saknas för ' . $companyMissingPrice);
            }
    
            $this->presenter->displayAssetNotices($result->assets);
        }

        Logger::getInstance()->printInfos();

        if ($options->displayLog) {
            Logger::getInstance()
                ->printNotices()
                ->printWarnings();
        }
    }

    private function calculateTWR(
        stdClass $portfolio,
        TransactionMapper $transactionMapper,
        ?string $filterDateFrom = null,
        ?string $filterDateTo = null
    ): void
    {
        echo 'Calculating TWR...' . PHP_EOL;

        $subPeriodDates = [];
        $subPeriodIndex = 0;
        $previousDate = null;

        $dateFrom = ($filterDateFrom ? new DateTime($filterDateFrom) : null);
        $dateTo = ($filterDateTo ? new DateTime($filterDateFrom) : null);
        
        $accountTransactions = [];
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

            if (!isset($subPeriodDates[$subPeriodIndex])) {
                $subPeriodDates[$subPeriodIndex] = [];
            }

            if (count($subPeriodDates[$subPeriodIndex]) === 2) {
                $previousDate = $subPeriodDates[$subPeriodIndex][1];
                $subPeriodIndex++;

                $subPeriodDates[$subPeriodIndex][] = $previousDate;

                if (!isset($subPeriodDates[$subPeriodIndex])) {
                    $subPeriodDates[$subPeriodIndex] = [];
                }
            }

            if (!in_array($transaction->getDateString(), $subPeriodDates[$subPeriodIndex])) {
                $subPeriodDates[$subPeriodIndex][] = $transaction->getDateString();
            }

            $accountTransactions[] = $transaction;
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

        $tickers = file_get_contents(ROOT_PATH . '/resources/tmp/tickers.json');
        $tickers = json_decode($tickers);

        $historicalCurrencyExchangeRateFile = ROOT_PATH . '/resources/tmp/historical_currency.csv';
        $historicalCurrencyExchangeRates = $this->getHistoricalExchangeRates($historicalCurrencyExchangeRateFile);

        $previousEndValue = 0;
        $index = 0;
        $twr = 1;
        $assets = [];
        $returns = [];
        foreach ($subPeriodDates as $subPeriod) {
            $startDateString = $subPeriod[0];
            $endDateString = $subPeriod[1];
            $startDate = new DateTime($subPeriod[0]);
            $endDate = new DateTime($subPeriod[1]);
            $dividendSum = 0;
            $startValue = $previousEndValue;

            $accountTransactionsInSubPeriod = array_filter($accountTransactions, function (Transaction $transaction) use ($startDate, $endDate, $index) {
                $transactionDate = $transaction->date;
                return $transactionDate >= $startDate && $transactionDate < $endDate;
            });

            $portfolioTransactionsInSubPeriod = array_filter($portfolioTransactions, function ($transaction) use ($startDate, $endDate, $index) {
                $transactionDate = $transaction->date;
                return $transactionDate >= $startDate && $transactionDate < $endDate;
            });

            $accountTransactionsInSubPeriod = array_values($accountTransactionsInSubPeriod);
            usort($accountTransactionsInSubPeriod, function (Transaction $a, Transaction $b): int {
                return strtotime($a->getDateString()) <=> strtotime($b->getDateString());
            });

            $portfolioTransactionsInSubPeriod = array_values($portfolioTransactionsInSubPeriod);
            usort($portfolioTransactionsInSubPeriod, function (Transaction $a, Transaction $b): int {
                return strtotime($a->getDateString()) <=> strtotime($b->getDateString());
            });

            // PORTFOLIO TRANSACTIONS
            foreach ($portfolioTransactionsInSubPeriod as $transaction) {
                if (!isset($assets[$transaction->isin])) {
                    $newAsset = new FinancialAsset();
                    $newAsset->name = $transaction->name;
                    $newAsset->isin = $transaction->isin;

                    $assets[$transaction->isin] = $newAsset;
                }

                $transactionMapper->addTransactionsToExistingAsset($assets[$transaction->isin], $transaction);
                if ($transaction->type === TransactionType::DIVIDEND) {
                    $dividendSum += $transaction->rawAmount;
                }
            }

            // ACCOUNT TRANSACTIONS
            $accountTransactionsSum = 0;
            foreach ($accountTransactionsInSubPeriod as $transaction) {
                if ($index === 0 && $transaction->date == $startDate) {
                    $startValue += $transaction->rawAmount;
                }
                $transactionMapper->handleNonAssetTransactionType($transaction);

                $accountTransactionsSum += $transaction->rawAmount;
            }

            $portfolioValue = 0;
            foreach ($assets as $asset) {
                if ($asset->getCurrentNumberOfShares() <= 0) {
                    continue;
                }

                $fileName = ROOT_PATH . '/resources/tmp/historical_prices/' . $asset->isin . '.json';

                // Cache historical prices
                if (!isset($historicalPricesCache[$asset->isin])) {
                    if (file_exists($fileName)) {
                        $historicalPricesCache[$asset->isin] = json_decode(file_get_contents($fileName), true);
                    } else {
                        $historicalPricesCache[$asset->isin] = [];
                    }
                }
                $historicalPrices = $historicalPricesCache[$asset->isin];

                $sharePrice = 0;
                if (empty($historicalPrices)) {
                    // Handle case when there are no historical prices. Use the last known price instead.

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

                    $sharePrice = abs($lastTransaction->rawAmount) / $lastTransaction->rawQuantity;

                    Logger::getInstance()->addNotice('Missing price for: ' . $asset->name . ' (' . $asset->isin . ')' . ' on ' . $endDateString . ', using last known price ' . $lastTransaction->getDateString() . ' (' . $sharePrice . ')');
                }

                // Kolla direkt på propertyn om datumet finns, annras börja loopa igenom
                if ($historicalPrices) {
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

            usort($transactionMapper->overview->cashFlows, function (Transaction $a, Transaction $b): int {
                return strtotime($a->getDateString()) <=> strtotime($b->getDateString());
            });
            $cashBalance = $transactionMapper->overview->calculateBalance($transactionMapper->overview->cashFlows);

            $index++;

            $endValue = $portfolioValue + $cashBalance + $dividendSum;
            $previousEndValue = $endValue;

            if ($index === 1) {
                $startValue = $accountTransactionsSum;
                $return = ($previousEndValue - $startValue) / $startValue;
            } else {
                $return = ($endValue - $startValue + $accountTransactionsSum) / $startValue;
            }

            // TODO: hur ska man hantera subperioder som inte innehåller några portföljtransaktioner?

            $twr *= (1 + $return);
            $returns[] = $return;

            
            print_r([
                'startDate' => $startDateString,
                'endDate' => $endDateString,
                'startValue' => $startValue,
                'endValue' => $endValue,
                'portfolioValue' => $portfolioValue,
                'accountTransactionsSum' => $accountTransactionsSum,
                'cashBalance' => $cashBalance,
                'dividendSum' => $dividendSum,
                'test' => ($previousEndValue - $startValue - $accountTransactionsSum),
                'return' => $return,
                'TWR' => $twr
            ]);
            
            

            if ($index === 3) {

            }

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

    private function getHistoricalExchangeRates(string $filename): array
    {
        $exchangeRates = [];
        if (($handle = fopen($filename, "r")) !== false) {
            $headers = fgetcsv($handle, 1000, ",");
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
