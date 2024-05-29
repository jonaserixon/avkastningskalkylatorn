<?php

namespace src\Libs\FileManager\CsvProcessor;

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
    protected function parseTransactions(string $fileName): array
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

                $holdings[$holding->getIsin()] = $holding;
            }
        }

        return $holdings;
    }

    public function getCurrentPriceByIsin(string $isin): ?float
    {
        if (empty($this->currentHoldingsData)) {
            $this->currentHoldingsData = $this->parseTransactions(''); // TODO: fixa detta.
        }

        foreach ($this->currentHoldingsData as $holding) {
            if ($holding->getIsin() === $isin) {
                return $holding->getPrice();
            }
        }

        return null;
    }

    public function getNameByIsin(string $isin): ?string
    {
        if (empty($this->currentHoldingsData)) {
            $this->currentHoldingsData = $this->parseTransactions(''); // TODO: fixa detta.
        }

        foreach ($this->currentHoldingsData as $holding) {
            if ($holding->getIsin() === $isin) {
                return $holding->getName();
            }
        }

        return null;
    }
}
