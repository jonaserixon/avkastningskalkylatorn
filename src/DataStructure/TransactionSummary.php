<?php

namespace src\DataStructure;

class TransactionSummary
{
    // TODO: remove "Amount" naming.
    public string $name;
    public array $transactionNames = [];
    public string $isin;
    public float $buyTotal = 0;
    public float $sellTotal = 0;
    public float $dividendTotal = 0;
    public float $feeTotal = 0;
    public float $taxTotal = 0;
    public float $interestTotal = 0; // Why does this exist?
    public float $commissionAmountTotal = 0; // TODO: remove this useless property
    public float $commissionSellAmountTotal = 0;
    public float $commissionBuyAmountTotal = 0;
    public float $currentNumberOfShares = 0; // float to handle fractional shares
    public ?float $currentPricePerShare = 0;
    public ?float $currentValueOfShares = 0;
    public string $firstTransactionDate; // TODO
    public string $lastTransactionDate; // TODO
    public float $depositAmountTotal = 0;
    public float $withdrawalAmountTotal = 0;
    public ?AssetReturn $assetReturn = null;
}
