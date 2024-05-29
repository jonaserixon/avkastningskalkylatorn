<?php

namespace src\DataStructure;

use DateTime;

class Transaction
{
    private DateTime $date;
    private string $bank; // private Bank $bank;
    private string $account;
    private string $type; // private TransactionType $type;
    private string $name;
    private ?string $description;
    private ?float $rawQuantity; // float to handle fractional shares
    private ?float $rawPrice;
    private ?float $pricePerShareSEK; // belopp / antal aktier
    private ?float $rawAmount;
    private ?float $commission; // brokerage commission
    private string $currency;
    private ?string $isin;

    public function __construct(
        DateTime $date,
        string $bank,
        string $account,
        string $type,
        string $name,
        ?string $description,
        ?float $rawQuantity,
        ?float $rawPrice,
        ?float $pricePerShareSEK,
        ?float $rawAmount,
        ?float $commission,
        string $currency,
        ?string $isin
    ) {
        $this->date = $date;
        $this->bank = $bank;
        $this->account = $account;
        $this->type = $type;
        $this->name = $name;
        $this->description = $description;
        $this->rawQuantity = $rawQuantity;
        $this->rawPrice = $rawPrice;
        $this->pricePerShareSEK = $pricePerShareSEK;
        $this->rawAmount = $rawAmount;
        $this->commission = $commission;
        $this->currency = $currency;
        $this->isin = $isin;
    }

    public function getDate(): DateTime
    {
        return $this->date;
    }

    public function getDateString(): string
    {
        return $this->date->format('Y-m-d');
    }

    public function getBank(): string
    {
        return $this->bank;
    }

    public function getAccount(): string
    {
        return $this->account;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getRawQuantity(): ?float
    {
        return $this->rawQuantity;
    }

    public function getRawPrice(): ?float
    {
        return $this->rawPrice;
    }

    public function getPricePerShareSEK(): ?float
    {
        return $this->pricePerShareSEK;
    }

    public function getRawAmount(): ?float
    {
        return $this->rawAmount;
    }

    public function getCommission(): ?float
    {
        return $this->commission;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getIsin(): ?string
    {
        return $this->isin;
    }
}
