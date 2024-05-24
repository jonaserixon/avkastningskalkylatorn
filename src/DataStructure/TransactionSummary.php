<?php

namespace src\DataStructure;

class TransactionSummary
{
    public string $name;
    public array $transactionNames = [];
    public string $isin;
    public float $buy = 0;
    public float $sell = 0;
    public float $dividend = 0;
    public float $commissionBuy = 0;
    public float $commissionSell = 0;
    public float $fee = 0;
    public float $foreignWithholdingTax = 0;
    public float $currentNumberOfShares = 0; // float to handle fractional shares
    public ?float $currentPricePerShare = 0;
    public ?float $currentValueOfShares = 0;
    public string $firstTransactionDate; // TODO
    public string $lastTransactionDate; // TODO
    public ?AssetReturn $assetReturn = null;
}
