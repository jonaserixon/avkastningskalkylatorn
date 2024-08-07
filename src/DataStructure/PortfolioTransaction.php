<?php

declare(strict_types=1);

namespace Avk\DataStructure;

class PortfolioTransaction
{
    public string $name;
    public string $isin;

    /**
     * @var Transaction[]
     */
    public array $transactions = [];

    /**
     * @param Transaction[] $transactions
     */
    public function __construct(string $name, string $isin, array $transactions)
    {
        $this->name = $name;
        $this->isin = $isin;
        $this->transactions = $transactions;
    }
}
