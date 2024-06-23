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

        $this->doThingsAndStuff($portfolio, $transactionMapper);

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

        Logger::getInstance()->printInfos();

        if ($options->displayLog) {
            Logger::getInstance()
                ->printNotices()
                ->printWarnings();
        }
    }

    private function doThingsAndStuff(stdClass $portfolio, TransactionMapper $transactionMapper): void
    {
        $subPeriodDates = [];
        $index = 0;
        $previousDate = null;
        
        $accountTransactions = [];
        foreach ($portfolio->accountTransactions as $row) {
            $transactionType = TransactionType::from($row->type);

            if (!in_array($transactionType, [TransactionType::DEPOSIT, TransactionType::WITHDRAWAL])) {
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

            if (!isset($subPeriodDates[$index])) {
                $subPeriodDates[$index] = [];
            }

            if (count($subPeriodDates[$index]) === 2) {
                $previousDate = $subPeriodDates[$index][1];
                $index++;

                $subPeriodDates[$index][] = $previousDate;

                if (!isset($subPeriodDates[$index])) {
                    $subPeriodDates[$index] = [];
                }
            }

            if (!in_array($transaction->getDateString(), $subPeriodDates[$index])) {
                $subPeriodDates[$index][] = $transaction->getDateString();
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
            // Sista subperioden har inte någon slutperiod.
            $subPeriodDates[count($subPeriodDates) - 1][] = date('Y-m-d');
        }

        $tickers = file_get_contents(ROOT_PATH . '/resources/tmp/tickers.json');
        $tickers = json_decode($tickers);

        $historicalCurrencyExchangeRateFile = ROOT_PATH . '/resources/tmp/historical_currency.csv';
        $historicalCurrencyExchangeRates = $this->getHistoricalExchangeRates($historicalCurrencyExchangeRateFile);

        $previousEndValue = 0;
        $previousCashBalance = 0;
        $assets = [];
        $index = 0;
        foreach ($subPeriodDates as $subPeriod) {
            $startDateString = $subPeriod[0];
            $endDateString = $subPeriod[1];
            $startDate = new DateTime($subPeriod[0]);
            $endDate = new DateTime($subPeriod[1]);

            $accountTransactionsInSubPeriod = array_filter($accountTransactions, function (Transaction $transaction) use ($startDate, $endDate, $index) {
                $transactionDate = $transaction->date;
                if ($index === 0) {
                    return $transactionDate >= $startDate && $transactionDate <= $endDate;
                } else {
                    return $transactionDate > $startDate && $transactionDate <= $endDate;
                }
            });
            $accountTransactionsInSubPeriod = array_values($accountTransactionsInSubPeriod);
            usort($accountTransactionsInSubPeriod, function (Transaction $a, Transaction $b): int {
                return strtotime($a->getDateString()) <=> strtotime($b->getDateString());
            });

            $portfolioTransactionsInSubPeriod = array_filter($portfolioTransactions, function ($transaction) use ($startDate, $endDate, $index) {
                $transactionDate = $transaction->date;
                if ($index === 0) {
                    return $transactionDate >= $startDate && $transactionDate <= $endDate;
                } else {
                    return $transactionDate > $startDate && $transactionDate <= $endDate;
                }
            });
            $portfolioTransactionsInSubPeriod = array_values($portfolioTransactionsInSubPeriod);
            usort($portfolioTransactionsInSubPeriod, function (Transaction $a, Transaction $b): int {
                return strtotime($a->getDateString()) <=> strtotime($b->getDateString());
            });

            $startValue = $previousEndValue;

            // PORTFOLIO TRANSACTIONS
            foreach ($portfolioTransactionsInSubPeriod as $transaction) {
                if (!isset($assets[$transaction->isin])) {
                    $newAsset = new FinancialAsset();
                    $newAsset->name = $transaction->name;
                    $newAsset->isin = $transaction->isin;

                    $assets[$transaction->isin] = $newAsset;
                }

                $transactionMapper->addTransactionsToExistingAsset($assets[$transaction->isin], $transaction);
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
                // Handle case when there are no historical prices. Use the last known price instead.
                if (empty($historicalPrices)) {
                    // echo 'Historical prices file not found for ' . $asset->name . ' (' . $asset->isin . ')' . ' on ' . $endDate . PHP_EOL;
                    $assetTransactions = $asset->getTransactions();
                    $lastTransaction = end($assetTransactions);
                    $sharePrice = $lastTransaction->rawAmount / $lastTransaction->rawQuantity;
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

                // $tickerIndex = array_search($asset->isin, array_column($tickers, 'isin'));
                // if ($tickerIndex === false) {
                //     throw new Exception('Currency not found for ' . $asset->isin);
                // }
                // $currency = $tickers[$tickerIndex]->currency;

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
                    foreach ($historicalCurrencyExchangeRates as $exchangeRateRow) {
                        if ($exchangeRateRow['Date'] === $endDateString) {
                            $sharePrice *= $exchangeRateRow[$currency];
                            break;
                        }
                    }
                }

                // echo 'Asset ' . $asset->name . ' (' . $asset->isin . ') has a price of ' . $sharePrice . ' on ' . $endDate . ', num. of shares: ' . $asset->getCurrentNumberOfShares() . ', total value of ' . $sharePrice * $asset->getCurrentNumberOfShares() . PHP_EOL;

                if ($asset->getCurrentNumberOfShares() > 0) {
                    $portfolioValue += $sharePrice * $asset->getCurrentNumberOfShares();
                }
            }

            // echo 'Total portfolio balance: ' . $portfolioValue . PHP_EOL;
            // echo '--------------------------------------------------' . PHP_EOL;

            usort($transactionMapper->overview->cashFlows, function (Transaction $a, Transaction $b): int {
                return strtotime($a->getDateString()) <=> strtotime($b->getDateString());
            });
            $cashBalance = $transactionMapper->overview->calculateBalance($transactionMapper->overview->cashFlows);

            $index++;
            $previousEndValue = $portfolioValue + $cashBalance;

            if ($startValue === 0) {
                $startValue = $accountTransactionsSum;
            }

            // TODO: hur ska man hantera subperioder som inte innehåller några portföljtransaktioner?

            print_r([
                'startDate' => $startDateString,
                'endDate' => $endDateString,
                'startValue' => $startValue,
                'endValue' => $previousEndValue,
                'portfolioValue' => $portfolioValue,
                'accountTransactionsSum' => $accountTransactionsSum,
                // 'portfolioTransactionSum' => $portfolioTransactionSum,
                'cashBalance' => $cashBalance,
            ]);

            // echo 'Sub period ' . $index . ' of ' . count($subPeriodDates) . PHP_EOL;
        }

        // print_r($assets);
        // exit;

        // print_r($subPeriodDates);
        // exit;
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
