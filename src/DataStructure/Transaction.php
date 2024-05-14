<?php

namespace src\DataStructure;

class Transaction
{
    public string $date;
    public string $bank;
    // public Bank $bank;
    public string $account;
    public string $type;
    // public TransactionType $type;
    public string $name;
    public float $quantity; // float to handle fractional shares
    public float $rawQuantity; // float to handle fractional shares
    public float $price;
    public float $amount;
    public float $fee;
    public string $currency;
    public string $isin;
}