<?php

namespace src\DataStructure;

class FinancialAsset
{
    public string $name;
    public string $isin;
    public float $buy = 0;
    public float $sell = 0;
    public float $dividend = 0;
    public float $commissionBuy = 0;
    public float $commissionSell = 0;
    public float $fee = 0;
    public float $preliminaryCurrencyExchangeRateFee = 0; // TODO: implement "preliminary" currency exchange rate based on isin
    public float $foreignWithholdingTax = 0;
    public float $currentNumberOfShares = 0; // float to handle fractional shares
    public ?float $currentPricePerShare = 0;
    public ?float $currentValueOfShares = 0;
    public string $firstTransactionDate;
    public string $lastTransactionDate;
    public ?AssetReturn $assetReturn = null;

    /** @var string[] */
    public array $transactionNames = [];

    /** @var string[] */
    public array $notices = []; // TODO: lägg till info om aktiesplittar, kurspris, värdepappersöverföringar, etc.

    /** @var string[] */
    public array $banks = [];

    /** @var string[] */
    public array $accounts = [];
}
