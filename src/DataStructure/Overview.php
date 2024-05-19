<?php

namespace src\DataStructure;

use DateTime;
use Exception;
use src\DataStructure\Transaction;

class Overview
{
    public float $totalBuyAmount = 0;
    public float $totalSellAmount = 0;
    public float $totalFee = 0;
    public float $totalDividend = 0;
    public float $totalCurrentHoldings = 0;
    public float $totalProfitInclFees = 0;

    public string $firstTransactionDate;
    public string $lastTransactionDate;

    /**
     * List of all transactions
     */
    public array $transactions = [];

    /**
     * List of asset-specific transactions (grouped by ISIN)
     */
    public array $assetTransactions = [];

    // TODO: think about where to put all of this shit.

    public function calculateCAGR()
    {

    }

    public function calculateXIRR(array $transactions)
    {
        $minDate = $transactions[0]->date;
        $minDate = new DateTime($minDate);

        // NPV (Net Present Value) function
        $npv = function ($rate) use ($transactions, $minDate) {
            $sum = 0;
            foreach ($transactions as $transaction) {
                $amount = $transaction->amount;
                $date = new DateTime($transaction->date);
                $days = $minDate->diff($date)->days;
                $sum += $amount / pow(1 + $rate, $days / 365);
            }
            return $sum;
        };

        // Newton-Raphson method to find the root
        $guess = 0.1;
        $tolerance = 0.0001;
        $maxIterations = 100;
        $iteration = 0;

        while ($iteration < $maxIterations) {
            $npvValue = $npv($guess);
            $npvDerivative = ($npv($guess + $tolerance) - $npvValue) / $tolerance;

            // Hantera liten derivata
            if (abs($npvDerivative) < $tolerance) {
                // Justera gissningen lite för att undvika division med noll
                $npvDerivative = $tolerance;
            }

            $newGuess = $guess - $npvValue / $npvDerivative;

            if (abs($newGuess - $guess) < $tolerance) {
                return $newGuess;
            }

            $guess = $newGuess;
            $iteration++;
        }

        throw new Exception("XIRR did not converge");
    }

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
