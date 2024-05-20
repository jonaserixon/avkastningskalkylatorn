<?php

namespace src\DataStructure;

class TransactionSummary
{
    public string $name;
    public array $transactionNames = [];
    public string $isin;
    public float $buyAmountTotal = 0;
    public float $sellAmountTotal = 0;
    public float $dividendAmountTotal = 0;
    public float $feeAmountTotal = 0; // TODO: dela upp i köp/sälj/ADR-avgifter etc.
    public float $feeSellAmountTotal = 0;
    public float $feeBuyAmountTotal = 0;
    public float $currentNumberOfShares = 0; // float to handle fractional shares
    public ?float $currentPricePerShare = 0;
    public ?float $currentValueOfShares = 0;
    public string $firstTransactionDate; // TODO
    public string $lastTransactionDate; // TODO
    public float $depositAmountTotal = 0;
    public float $withdrawalAmountTotal = 0;
    public ?AssetReturn $assetReturn = null;
}
