<?php

namespace src\Libs\FileManager;

use src\DataStructure\Holding;

class StockPriceManager
{
    /**
     * @var Holding[]
     */
    private array $currentHoldingsData = [];

    /**
     * Based off of this template: https://docs.google.com/spreadsheets/d/10dohImvsGkBNfA_qB5EATt3tX01UKdmBozDhD7bMB18/edit?usp=sharing
     * @return Holding[]
     */
    private function getStockPricesForCurrentHoldings(): array
    {
        $files = glob(STOCK_PRICE_DIR . '/*.csv');

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

            while (($fields = fgetcsv($file, 0, ",")) !== false) {
                $holding = new Holding();

                $holding->name = $fields[0];
                $holding->isin = trim($fields[1]);
                $holding->price = (float) $fields[3];

                $holdings[$holding->isin] = $holding;
            }
        }

        return $holdings;
    }

    public function getCurrentPriceByIsin(string $isin): ?float
    {
        if (!$this->currentHoldingsData) {
            $this->currentHoldingsData = $this->getStockPricesForCurrentHoldings();
        }

        foreach ($this->currentHoldingsData as $holding) {
            if ($holding->isin === $isin) {
                return $holding->price;
            }
        }

        return null;
    }
}
