<?php

class TransactionSummary
{
    public string $name;
    public string $isin;
    public float $buyAmountTotal = 0;
    public float $sellAmountTotal = 0;
    public float $dividendAmountTotal = 0;
    public float $feeAmountTotal = 0;
    public int $currentNumberOfShares = 0;
    // TODO: lägg till antal köp/sälj/utdelningar
}
