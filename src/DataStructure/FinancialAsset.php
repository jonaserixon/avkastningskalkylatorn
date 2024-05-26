<?php

namespace src\DataStructure;

class FinancialAsset
{
    public string $name;
    public string $isin;
    private float $buy = 0;
    private float $sell = 0;
    private float $dividend = 0;
    private float $commissionBuy = 0;
    private float $commissionSell = 0;
    private float $fee = 0;
    public float $preliminaryCurrencyExchangeRateFee = 0; // TODO: implement "preliminary" currency exchange rate based on isin
    private float $foreignWithholdingTax = 0;
    private float $currentNumberOfShares = 0; // float to handle fractional shares
    private ?float $currentPricePerShare = 0;
    private ?float $currentValueOfShares = 0;
    public string $firstTransactionDate;
    public string $lastTransactionDate;
    public ?AssetReturn $assetReturn = null;

    // TODO: move this to the AssetReturn structure.
    public float $realizedGainLoss = 0;
    public float $unrealizedGainLoss = 0;
    public float $costBasis = 0;

    /** @var string[] */
    public array $transactionNames = [];

    /** @var string[] */
    public array $notices = []; // TODO: lägg till info om aktiesplittar, kurspris, värdepappersöverföringar, etc.

    /** @var mixed[] */
    public array $bankAccounts = [];

    public function addBuy(float $amount): void
    {
        $this->buy += round($amount, 3);
    }

    public function getBuyAmount(): float
    {
        return $this->buy;
    }

    public function addSell(float $amount): void
    {
        $this->sell += round($amount, 3);
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
        $this->currentNumberOfShares += round($amount, 3);
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
        $this->currentPricePerShare = round($price, 3);
    }

    public function getCurrentValueOfShares(): ?float
    {
        return $this->currentValueOfShares;
    }

    public function setCurrentValueOfShares(float $value): void
    {
        $this->currentValueOfShares = round($value, 3);
    }

    public function getFirstTransactionDate(): string
    {
        return $this->firstTransactionDate;
    }

    public function setFirstTransactionDate(string $date): void
    {
        $this->firstTransactionDate = $date;
    }

    public function getLastTransactionDate(): string
    {
        return $this->lastTransactionDate;
    }

    public function setLastTransactionDate(string $date): void
    {
        $this->lastTransactionDate = $date;
    }
}
