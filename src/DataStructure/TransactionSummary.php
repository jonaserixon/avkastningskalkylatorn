<?php

class TransactionSummary
{
    public string $name;
    public string $isin;
    public float $buyAmountTotal = 0;
    public float $sellAmountTotal = 0;
    public float $dividendAmountTotal = 0;
    public float $feeAmountTotal = 0; // TODO: dela upp i köp/sälj/ADR-avgifter etc.
    public float $feeSellAmountTotal = 0;
    public float $feeBuyAmountTotal = 0;
    public int $currentNumberOfShares = 0;
}
