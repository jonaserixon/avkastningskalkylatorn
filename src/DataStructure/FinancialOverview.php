<?php declare(strict_types=1);

namespace Avk\DataStructure;

use Exception;
use Avk\DataStructure\Transaction;
use Avk\Enum\Bank;
use Avk\Enum\TransactionType;

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
    private ?AssetPerformance $performance = null;

    /**
     * List of all cash flows
     *
     * @var Transaction[]
     */
    public array $cashFlows = [];

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
            null,
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
            if ($transaction->rawAmount === null) {
                continue;
            }

            $amount = round($transaction->rawAmount, 4);
            $balance += $amount;
        }

        return $balance;
    }

    public function getPerformance(): AssetPerformance
    {
        if ($this->performance === null) {
            $this->performance = new AssetPerformance();
        }

        return $this->performance;
    }

    public function setPerformance(AssetPerformance $performance): void
    {
        $this->performance = $performance;
    }

    public function getFirstTransactionDate(): ?string
    {
        return $this->cashFlows[0]->getDateString();
    }

    public function getLastTransactionDate(): ?string
    {
        return $this->cashFlows[count($this->cashFlows) - 1]->getDateString();
    }
}
