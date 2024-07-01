<?php declare(strict_types=1);

namespace Avk\DataStructure;

readonly class Holding
{
    public string $name;
    public string $isin;
    public float $price;

    public function __construct(string $name, string $isin, float $price)
    {
        $this->name = $name;
        $this->isin = $isin;
        $this->price = $price;
    }
}
