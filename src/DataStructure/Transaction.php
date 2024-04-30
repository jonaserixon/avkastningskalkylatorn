<?php

class Transaction
{
    public string $date;
    public string $bank;
    // public Bank $bank;
    public string $account;
    public string $transactionType;
    // public TransactionType $transactionType;
    public string $name;
    public int $quantity;
    public int $rawQuantity;
    public float $price;
    public float $amount;
    public float $fee;
    public string $currency;
    public string $isin;
}
