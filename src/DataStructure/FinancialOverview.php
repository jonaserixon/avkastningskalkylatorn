<?php

namespace src\DataStructure;

use src\DataStructure\Transaction;

// TODO: more consistent naming.

class FinancialOverview
{
    public float $totalBuyAmount = 0;
    public float $totalSellAmount = 0;
    public float $totalBuyCommission = 0;
    public float $totalSellCommission = 0;
    public float $totalDividend = 0;
    public float $totalInterest = 0;
    public float $totalFee = 0;
    public float $totalTax = 0;
    public float $totalForeignWithholdingTax = 0;
    public float $totalReturnedForeignWithholdingTax = 0;
    public float $totalCurrentHoldings = 0;
    public array $currentHoldingsWeighting = [];
    public float $depositAmountTotal = 0;
    public float $withdrawalAmountTotal = 0;
    public string $firstTransactionDate;
    public string $lastTransactionDate;
    public AssetReturn $returns;
    // public float $totalProfitInclFees = 0;

    /**
     * List of all cash flows
     *
     * @var Transaction[]
     */
    public array $cashFlows = [];

    // TODO: think about where to put all of this shit.

    public function addCashFlow(string $date, float $amount, string $name, string $type, string $account, string $bank): void
    {
        $transaction = new Transaction();
        $transaction->date = $date;
        $transaction->rawAmount = $amount;
        $transaction->name = $name;
        $transaction->type = $type;
        $transaction->account = $account;
        $transaction->bank = $bank;

        $this->cashFlows[] = $transaction;
    }

    public function calculateBalance(array $transactions): float
    {
        $balance = 0;
        foreach ($transactions as $transaction) {
            $amount = round($transaction->rawAmount, 4);
            $balance += $amount;
        }

        return $balance;
    }
}
