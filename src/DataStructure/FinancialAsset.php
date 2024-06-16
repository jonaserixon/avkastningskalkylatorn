<?php

namespace src\DataStructure;

use src\Enum\TransactionType;

class FinancialAsset
{
    public string $name = '';
    public ?string $isin = null;
    private float $buy = 0;
    private float $sell = 0;
    private float $dividend = 0;
    private float $commissionBuy = 0;
    private float $commissionSell = 0;
    private float $fee = 0;
    public float $preliminaryCurrencyExchangeRateFee = 0; // TODO: implement "preliminary" currency exchange rate based on isin
    private float $foreignWithholdingTax = 0;
    private float $currentNumberOfShares = 0;
    private ?float $currentPricePerShare = 0;
    private ?float $currentValueOfShares = 0;
    private ?string $firstTransactionDate = null;
    private ?string $lastTransactionDate = null;
    public ?AssetReturn $assetReturn = null;

    // TODO: move this to the AssetReturn structure.
    public float $realizedGainLoss = 0;
    public float $unrealizedGainLoss = 0;
    public float $costBasis = 0;
    //

    /** @var string[] */
    public array $transactionNames = [];

    /** @var string[] */
    public array $notices = [];

    /** @var mixed[] */
    public array $bankAccounts = [];

    /** @var Transaction[] */
    private array $transactions = [];

    /**
     * @return Transaction[]
     */
    public function getTransactions(): array
    {
        return $this->transactions;
    }

    /**
     * @param TransactionType $type
     * @return Transaction[]
     */
    public function getTransactionsByType(TransactionType $type): array
    {
        $matchedTransactions = [];
        foreach ($this->transactions as $transaction) {
            if ($transaction->type === $type) {
                $matchedTransactions[] = $transaction;
            }
        }

        return $matchedTransactions;
    }

    public function hasTransactionOfType(TransactionType $type): bool
    {
        foreach ($this->transactions as $transaction) {
            if ($transaction->type === $type) {
                return true;
            }
        }

        return false;
    }

    public function addTransaction(Transaction $transaction): void
    {
        $this->transactions[] = $transaction;
    }

    public function addBuy(float $amount): void
    {
        $this->buy += round($amount, 3);
        // $this->buy = round($this->buy + round($amount, 3), 3);
    }

    public function getBuyAmount(): float
    {
        return $this->buy;
    }

    public function addSell(float $amount): void
    {
        $this->sell += round($amount, 3);
        // $this->sell = round($this->sell + round($amount, 3), 3);
    }

    public function getSellAmount(): float
    {
        return $this->sell;
    }

    public function addDividend(float $amount): void
    {
        $this->dividend += round($amount, 3);
    }

    public function getDividendAmount(): float
    {
        return $this->dividend;
    }

    public function addCommissionBuy(float $amount): void
    {
        $this->commissionBuy += round($amount, 2);
    }

    public function getCommissionBuyAmount(): float
    {
        return $this->commissionBuy;
    }

    public function addCommissionSell(float $amount): void
    {
        $this->commissionSell += round($amount, 3);
    }

    public function getCommissionSellAmount(): float
    {
        return $this->commissionSell;
    }

    public function addFee(float $amount): void
    {
        $this->fee += round($amount, 3);
    }

    public function getFeeAmount(): float
    {
        return $this->fee;
    }

    public function addForeignWithholdingTax(float $amount): void
    {
        $this->foreignWithholdingTax += $amount;
    }

    public function getForeignWithholdingTaxAmount(): float
    {
        return $this->foreignWithholdingTax;
    }

    public function addCurrentNumberOfShares(float $amount): void
    {
        $this->currentNumberOfShares += $amount;
        // $this->currentNumberOfShares = round($this->currentNumberOfShares + round($amount, 2), 2);
    }

    public function resetCurrentNumberOfShares(): void
    {
        $this->currentNumberOfShares = 0;
    }

    public function getCurrentNumberOfShares(): float
    {

        return $this->currentNumberOfShares;
    }

    public function getCurrentPricePerShare(): ?float
    {
        return $this->currentPricePerShare;
    }

    public function setCurrentPricePerShare(float $price): void
    {
        $this->currentPricePerShare = $price;
    }

    public function getCurrentValueOfShares(): ?float
    {
        return $this->currentValueOfShares;
    }

    public function setCurrentValueOfShares(float $value): void
    {
        $this->currentValueOfShares = round($value, 3);
    }

    public function getFirstTransactionDate(): ?string
    {
        return $this->firstTransactionDate;
    }

    public function setFirstTransactionDate(string $date): void
    {
        $this->firstTransactionDate = $date;
    }

    public function getLastTransactionDate(): ?string
    {
        return $this->lastTransactionDate;
    }

    public function setLastTransactionDate(string $date): void
    {
        $this->lastTransactionDate = $date;
    }

    public function getRealizedGainLoss(): float
    {
        return $this->realizedGainLoss;
    }

    public function setRealizedGainLoss(float $amount): void
    {
        $this->realizedGainLoss = $amount;
    }

    public function getUnrealizedGainLoss(): float
    {
        return $this->unrealizedGainLoss;
    }

    public function setUnrealizedGainLoss(float $amount): void
    {
        $this->unrealizedGainLoss = $amount;
    }

    public function getCostBasis(): float
    {
        return $this->costBasis;
    }

    public function setCostBasis(float $amount): void
    {
        $this->costBasis = $amount;
    }

    /**
     * @return mixed[]
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'isin' => $this->isin,
            'transactions' => $this->transactions
        ];
    }
}
