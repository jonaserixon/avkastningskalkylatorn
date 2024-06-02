<?php

namespace src\Command;

use Exception;
use src\Command\CommandProcessor;
use src\DataStructure\FinancialAsset;
use src\Enum\TransactionType;
use src\Service\API\Eod\EodWrapper;
use src\Service\FileManager\Exporter;
use src\Service\Transaction\TransactionLoader;
use src\View\TextColorizer;
use stdClass;

class FetchHistoricalPriceCommand extends CommandProcessor
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
        $options->bank = $this->options['bank'] ?? null;
        $options->isin = $this->options['isin'] ?? null;
        $options->asset = $this->options['asset'] ?? null;
        $options->dateFrom = $this->options['date-from'] ?? null;
        $options->dateTo = $this->options['date-to'] ?? null;
        $options->currentHoldings = $this->options['current-holdings'] ?? $commandOptions['current-holdings']['default'];
        $options->account = $this->options['account'] ?? null;

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

        // $listOfDates = [];
        // foreach ($isinsWithDates as $isin => $dates) {
        //     foreach ($dates as $date) {
        //         if (!in_array($date, $listOfDates)) {
        //             $listOfDates[] = $date;
        //         }
        //     }
        // }
        // Exporter::exportToCsv(['Date'], $listOfDates, 'dates.csv');
        // print_r($listOfDates);
        // exit;

        /*
        // Hämta ut tickers + exchange
        $this->tickerPicker($assets);

        // Hämta alla datum för insättningar, uttag och utdelningar.
        $isinsWithDates = $this->getIsinWithDates($transactions, $transactionLoader);

        // 2. Hämta historiska priser för varje ticker.
        $this->getHistoricalPrices($isinsWithDates);
        */
    }

    private function getTickerInfo(string $isin): ?stdClass
    {
        $file_path = ROOT_PATH . '/tmp/tickers.json';
        $jsonData = file_get_contents($file_path);
        $tickers = json_decode($jsonData);

        foreach ($tickers as $ticker) {
            if ($ticker->isin === $isin) {
                $result = new stdClass();
                $result->ticker = $ticker->ticker;
                $result->name = $ticker->name;
                $result->currency = $ticker->currency;
                $result->isin = $ticker->isin;

                return $result;
            }
        }

        return null;
    }

    /**
     * @param FinancialAsset[] $assets
     */
    private function tickerPicker(array $assets): void
    {
        $eodApi = new EodWrapper();

        $data = [];
        $file_path = ROOT_PATH . '/tmp/tickers.json';
        if (file_exists($file_path) && filesize($file_path) > 0) {
            $jsonData = file_get_contents($file_path);
            $data = json_decode($jsonData, true); // true för att få en associativ array
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON decode error: " . json_last_error_msg());
            }
        }
       
        foreach ($assets as $asset) {
            if (!$asset->isin) {
                continue;
            }

            echo $asset->name . ", ISIN: " . $asset->isin . PHP_EOL;
            $tickerSearch = $eodApi->searchForTickers($asset->isin);

            if (empty($tickerSearch)) {
                $assetNames = explode(' ', $asset->name);
                $tickerSearch = $eodApi->searchForTickers($assetNames[0] . ' ' . $assetNames[1]);
            }

            $tickerResult = [];
            $codeExchange = null;
            foreach ($tickerSearch as $row) {
                $tickerResult[] = $row->Code . '.' . $row->Exchange . ' - ' . $row->Name . ' (' . $row->Currency . ')';
            }

            $selectedTicker = null;
            if (count($tickerSearch) > 1) {
                foreach ($tickerSearch as $index => $ticker) {
                    echo TextColorizer::colorText($index + 1, 'blue') . ". Ticker: " . TextColorizer::colorText($ticker->Code . '.' . $ticker->Exchange, 'cyan') . " ({$ticker->Name}) - {$ticker->Currency}" . PHP_EOL;
                }

                echo "Välj en ticker genom att ange numret eller 0 för att skippa: ";
                $handle = fopen("php://stdin", "r");
                $choice = intval(fgets($handle));

                if ($choice === 0) {
                    $selectedTicker = null;
                } elseif ($choice > 0 && $choice <= count($tickerSearch)) {
                    $selectedTicker = $tickerSearch[$choice - 1];
                    $codeExchange = $selectedTicker->Code . '.' . $selectedTicker->Exchange;
                    echo "Du har valt ticker: '{$tickerResult[$choice - 1]}'\n";
                } else {
                    echo "Ogiltigt val, försök igen.\n";
                }
            } elseif (count($tickerSearch) === 1) {
                $selectedTicker = $tickerSearch[0];
                $codeExchange = $selectedTicker->Code . '.' . $selectedTicker->Exchange;
            } else {
                echo TextColorizer::colorText("Inga tickers hittades för denna ISIN", 'red') . PHP_EOL;
            }

            $data[] = [
                'isin' => $asset->isin,
                'ticker' => $codeExchange,
                'currency' => $selectedTicker->Currency ?? null,
                'name' => $asset->name
            ];

            echo "-----------------\n";

            $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON encode error: " . json_last_error_msg());
            }

            $result = file_put_contents($file_path, $jsonData);
            if ($result === false) {
                throw new Exception("Failed to write to file: {$file_path}");
            }
        }
    }

    private function getIsinWithDates(array $transactions, TransactionLoader $transactionLoader): array
    {
        $transactionDates = [];
        foreach ($transactions as $transaction) {
            if (!in_array($transaction->type, [
                TransactionType::DEPOSIT,
                TransactionType::WITHDRAWAL,
                TransactionType::DIVIDEND
            ])) {
                continue;
            }

            if (!in_array($transaction->date, $transactionDates)) {
                $transactionDates[] = $transaction->date;
            }
        }

        // Titta vilka innehav jag har för varje datum och hämta historiska priser för dessa.
        $isinsWithDates = [];
        foreach ($transactionDates as $transactionDate) {
            // beräkna assets till transaktionsdatumet.

            $assets = $transactionLoader->getFinancialAssets($transactions, $transactionDate);

            if (!empty($assets)) {
                foreach ($assets as $asset) {
                    if ($asset->getCurrentNumberOfShares() <= 0) {
                        continue;
                    }
                    if (!isset($isinsWithDates[$asset->isin])) {
                        $isinsWithDates[$asset->isin] = [];
                    }
                    if (!in_array($transactionDate->format('Y-m-d'), $isinsWithDates[$asset->isin])) {
                        $isinsWithDates[$asset->isin][] = $transactionDate->format('Y-m-d');
                    }
                }
            }
        }

        return $isinsWithDates;
    }

    private function getHistoricalPrices(array $isinsWithDates): void
    {
        $eodApi = new EodWrapper();

        $file_path = ROOT_PATH . '/tmp/historical_prices.json';
        if (file_exists($file_path) && filesize($file_path) > 0) {
            $jsonData = file_get_contents($file_path);
            $data = json_decode($jsonData, true); // true för att få en associativ array
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON decode error: " . json_last_error_msg());
            }
        }

        foreach ($isinsWithDates as $isin => $dates) {
            $tickerInfo = $this->getTickerInfo($isin);
            if (!$tickerInfo || empty($tickerInfo->ticker)) {
                echo "No ticker found for ISIN: " . $isin . PHP_EOL;
                continue;
            }

            $dateFrom = min($dates);
            $dateTo = max($dates);

            echo "ISIN: " . $isin . ", Ticker: " . $tickerInfo->ticker . PHP_EOL;

            $historicalPrices = $eodApi->getHistoricalPricesByTicker($tickerInfo->ticker, $dateFrom, $dateTo);
            $datePrices = [];
            foreach ($historicalPrices as $price) {
                if (in_array($price->date, $dates)) {
                    $datePrices[$price->date] = $price->close;
                }
            }

            $data[] = [
                'isin' => $isin,
                'ticker' => $tickerInfo->ticker,
                'name' => $tickerInfo->name,
                'currency' => $tickerInfo->currency,
                'historical_prices' => $datePrices
            ];

            $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON encode error: " . json_last_error_msg());
            }

            $result = file_put_contents($file_path, $jsonData);
            if ($result === false) {
                throw new Exception("Failed to write to file: {$file_path}");
            }
        }
    }
}
