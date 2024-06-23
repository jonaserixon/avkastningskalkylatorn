<?php declare(strict_types=1);

namespace src\DataStructure;

use DateTime;
use src\Enum\Bank;
use src\Enum\TransactionType;

readonly class Transaction
{
    public DateTime $date;
    public Bank $bank;
    public string $account;
    public TransactionType $type;
    public string $name;
    public ?string $description;
    public ?float $rawQuantity; // float to handle fractional shares
    public ?float $rawPrice;
    public ?float $pricePerShareSEK; // belopp / antal aktier
    public ?float $rawAmount;
    public ?float $commission; // brokerage commission
    public string $currency;
    public ?string $isin;
    public ?float $exchangeRate;

    public function __construct(
        DateTime $date,
        Bank $bank,
        string $account,
        TransactionType $type,
        string $name,
        ?string $description,
        ?float $rawQuantity,
        ?float $rawPrice,
        ?float $pricePerShareSEK,
        ?float $rawAmount,
        ?float $commission,
        string $currency,
        ?string $isin,
        ?float $exchangeRate
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
        $this->exchangeRate = $exchangeRate;
    }

    public function getDateString(): string
    {
        return $this->date->format('Y-m-d');
    }

    public function getBankName(): string
    {
        return $this->bank->value;
    }

    public function getTypeName(): string
    {
        return $this->type->value;
    }
}
