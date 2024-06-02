<?php

namespace src\Service\FileManager\CsvProcessor;

use Exception;
use src\DataStructure\Holding;

class StockPrice
{
    protected static string $DIR = STOCK_PRICE_DIR;
    protected const CSV_SEPARATOR = ",";

    /**
     * @var Holding[]
     */
    private array $currentHoldingsData = [];

    protected function validateImportFile(string $filePath): bool
    {
        $handle = fopen($filePath, "r");
        if ($handle === false) {
            throw new Exception('Failed to open file: ' . basename($filePath));
        }

        $headers = fgetcsv($handle, 1000, static::CSV_SEPARATOR);
        if ($headers === false) {
            throw new Exception('Failed to read headers from file: ' . basename($filePath));
        }

        if (count($headers) >= 5) {
            throw new Exception('Invalid stock price import file: ' . basename($filePath));
        }

        fclose($handle);

        return true;
    }

    /**
     * Based off of this template: https://docs.google.com/spreadsheets/d/10dohImvsGkBNfA_qB5EATt3tX01UKdmBozDhD7bMB18/edit?usp=sharing
     * @return Holding[]
     */
    protected function parseTransactions(): array
    {
        $files = glob(STOCK_PRICE_DIR . '/*.csv');
        if (empty($files)) {
            return [];
        }

        $latestFile = '';
        $latestTime = 0;
        foreach ($files as $file) {
            $filePath = $file;

            $fileTime = filemtime($filePath);

            if ($fileTime > $latestTime) {
                $latestTime = $fileTime;
                $latestFile = $filePath;
            }
        }

        $holdings = [];
        $file = fopen($latestFile, 'r');
        if ($file !== false) {
            fgetcsv($file);

            while (($fields = fgetcsv($file, 0, static::CSV_SEPARATOR)) !== false) {
                $holding = new Holding(
                    name: trim($fields[0]),
                    isin: trim($fields[1]),
                    price: (float) $fields[3]
                );

                $holdings[$holding->isin] = $holding;
            }
        }

        return $holdings;
    }

    public function getPriceByIsinAndDate(string $isin, string $dateTo): ?float
    {
        // Load historical prices
        $file_path = ROOT_PATH . '/tmp/historical_prices.json';
        $jsonData = file_get_contents($file_path);
        $historicalPrices = json_decode($jsonData);

        // combined_historical_currency_stock_price.json

        $file = fopen(ROOT_PATH . '/tmp/historical_currency.csv', 'r');
        fgetcsv($file); // Skip headers

        // Parse and structure historical currency exchange rates
        $historicalCurrencyExchangeRates = [];
        while (($fields = fgetcsv($file, 0, ',')) !== false) {
            // $historicalCurrencyExchangeRates[] = $fields;

            $date = $fields[0];
            $usdSek = $fields[1];
            $cadSek = $fields[2];
            $eurSek = $fields[3];
            $dkkSek = $fields[4];
            $nokSek = $fields[5];
            $sek = $fields[6];

            $historicalCurrencyExchangeRates[$date] = [
                'USD' => $usdSek,
                'CAD' => $cadSek,
                'EUR' => $eurSek,
                'DKK' => $dkkSek,
                'NOK' => $nokSek,
                'SEK' => $sek
            ];
        }
        fclose($file);

        // Convert historical prices to SEK
        foreach ($historicalPrices as $priceInfo) {
            foreach ($priceInfo->historical_prices as $date => $price) {
                if ($date !== $dateTo) {
                    continue;
                }
                $currency = $priceInfo->currency;
                if (!isset($historicalCurrencyExchangeRates[$date][$currency])) {
                    throw new Exception("Missing currency exchange rate for {$currency} on {$date} for ISIN {$priceInfo->isin}");
                }

                $currencyExchangeRate = $historicalCurrencyExchangeRates[$date][$currency];

                // $priceInSek = round($price * $currencyExchangeRate, 3);
                $priceInSek = $price * $currencyExchangeRate;

                $priceInfo->historical_prices->{$date} = $priceInSek;
            }
        }

        // Find the price for the given ISIN and date
        foreach ($historicalPrices as $priceInfo) {
            if ($priceInfo->isin === $isin) {
                if (!isset($priceInfo->historical_prices->{$dateTo})) {
                    // throw new Exception("Missing price for ISIN {$priceInfo->isin} on {$dateTo}");
                    return null;
                }

                return $priceInfo->historical_prices->{$dateTo};
            }
        }

        return null;
    }

    public function getCurrentPriceByIsin(string $isin): ?float
    {
        if (empty($this->currentHoldingsData)) {
            $this->currentHoldingsData = $this->parseTransactions();
        }

        foreach ($this->currentHoldingsData as $holding) {
            if ($holding->isin === $isin) {
                return $holding->price;
            }
        }

        return null;
    }

    public function getNameByIsin(string $isin): ?string
    {
        if (empty($this->currentHoldingsData)) {
            $this->currentHoldingsData = $this->parseTransactions();
        }

        foreach ($this->currentHoldingsData as $holding) {
            if ($holding->isin === $isin) {
                return $holding->name;
            }
        }

        return null;
    }
}
