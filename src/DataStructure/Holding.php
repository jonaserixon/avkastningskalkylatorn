<?php

namespace src\DataStructure;

class Holding
{
    private string $name;
    private string $isin;
    private float $price;

    public function __construct(string $name, string $isin, float $price)
    {
        $this->name = $name;
        $this->isin = $isin;
        $this->price = $price;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getIsin(): string
    {
        return $this->isin;
    }

    public function getPrice(): float
    {
        return $this->price;
    }
}
