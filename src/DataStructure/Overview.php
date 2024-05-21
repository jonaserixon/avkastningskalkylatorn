<?php

namespace src\DataStructure;

use src\DataStructure\Transaction;

class Overview
{
    public float $totalBuyAmount = 0;
    public float $totalSellAmount = 0;
    public float $totalCommission = 0; // TODO: remove this useless property
    public float $totalSellCommission = 0;
    public float $totalBuyCommission = 0;
    public float $totalDividend = 0;
    public float $totalInterest = 0;
    public float $totalFee = 0;
    public float $totalTax = 0;
    public float $totalCurrentHoldings = 0;
    public float $totalProfitInclFees = 0;
    public array $currentHoldingsWeighting = [];
    public float $depositAmountTotal = 0;
    public float $withdrawalAmountTotal = 0;

    public string $firstTransactionDate;
    public string $lastTransactionDate;

    public AssetReturn $returns;

    /**
     * List of all cash flows
     */
    public array $cashFlows = [];

    /**
     * List of asset-specific cash flows (grouped by ISIN)
     */
    /*
    public array $assetCashFlows = [];
    */

    // TODO: think about where to put all of this shit.

    public function addCashFlow(string $date, float $amount, string $name, string $type): void
    {
        $transaction = new Transaction();
        $transaction->date = $date;
        $transaction->amount = $amount;
        $transaction->name = $name;
        $transaction->type = $type;

        $this->cashFlows[] = $transaction;
    }

    public function addFinalCashFlow(float $currentMarketValue, string $name)
    {
        $this->lastTransactionDate = date('Y-m-d');
        $this->addCashFlow($this->lastTransactionDate, $currentMarketValue, 'Current total holdings: ' . $name, 'current_holding_value');
    }

    /*
    public function addAssetCashFlow(string $isin, string $date, float $amount)
    {
        $transaction = new Transaction();
        $transaction->date = $date;
        $transaction->amount = $amount;

        $this->assetCashFlows[$isin][] = $transaction;
    }
    *()

    /**
     * Run this method to add the current balance of an asset to the overview.
     */
    /*
    public function addFinalAssetCashFlow(string $isin, float $currentMarketValue)
    {
        $this->addAssetCashFlow($isin, date('Y-m-d'), $currentMarketValue);
    }
    */

    public function calculateBalance(array $transactions): float
    {
        $balance = 0;
        foreach ($transactions as $transaction) {
            $amount = round($transaction->amount, 2);
            $balance += $amount;
        }

        return $balance;
    }
}
