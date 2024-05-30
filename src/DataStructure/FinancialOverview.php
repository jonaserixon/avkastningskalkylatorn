<?php

namespace src\DataStructure;

use Exception;
use src\DataStructure\Transaction;
use src\Enum\Bank;
use src\Enum\TransactionType;

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
    public float $totalShareLoanPayout = 0;

    /** @var array<string, float> */
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

    public function addCashFlow(string $date, float $amount, string $name, TransactionType $type, string $account, Bank $bank): void
    {
        $dateTime = date_create($date);
        if ($dateTime === false) {
            throw new Exception('Invalid date while trying to add cash flow: ' . $date);
        }

        $transaction = new Transaction(
            $dateTime,
            $bank,
            $account,
            $type,
            $name,
            null,
            0,
            0,
            null,
            $amount,
            null,
            'SEK',
            null
        );

        $this->cashFlows[] = $transaction;
    }

    /**
     * @param Transaction[] $transactions
     */
    public function calculateBalance(array $transactions): float
    {
        $balance = 0;
        foreach ($transactions as $transaction) {
            if ($transaction->getRawAmount() === null) {
                continue;
            }

            $amount = round($transaction->getRawAmount(), 4);
            $balance += $amount;
        }

        return $balance;
    }
}
