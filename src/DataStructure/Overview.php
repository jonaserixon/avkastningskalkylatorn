<?php

namespace src\DataStructure;

use src\DataStructure\Transaction;

class Overview
{
    public float $totalBuyAmount = 0;
    public float $totalSellAmount = 0;
    public float $totalFee = 0;
    public float $totalSellFee = 0;
    public float $totalBuyFee = 0;
    public float $totalDividend = 0;
    public float $totalCurrentHoldings = 0;
    public float $totalProfitInclFees = 0;

    public string $firstTransactionDate;
    public string $lastTransactionDate;

    public AssetReturn $returns;

    /**
     * List of all transactions
     */
    public array $transactions = [];

    /**
     * List of asset-specific transactions (grouped by ISIN)
     */
    public array $assetTransactions = [];

    // TODO: think about where to put all of this shit.

    public function addTransaction(string $date, float $amount)
    {
        $transaction = new Transaction();
        $transaction->date = $date;
        $transaction->amount = $amount;

        $this->transactions[] = $transaction;
    }

    public function addFinalTransaction(float $currentMarketValue)
    {
        $this->lastTransactionDate = date('Y-m-d');
        $this->addTransaction($this->lastTransactionDate, $currentMarketValue);
    }

    public function addAssetTransaction(string $isin, string $date, float $amount)
    {
        $transaction = new Transaction();
        $transaction->date = $date;
        $transaction->amount = $amount;

        $this->assetTransactions[$isin][] = $transaction;
    }

    /**
     * Run this method to add the current balance of an asset to the overview.
     */
    public function addFinalAssetTransaction(string $isin, float $currentMarketValue)
    {
        $this->addAssetTransaction($isin, date('Y-m-d'), $currentMarketValue);
    }
}
