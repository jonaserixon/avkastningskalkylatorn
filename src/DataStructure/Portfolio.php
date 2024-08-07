<?php

declare(strict_types=1);

namespace Avk\DataStructure;

class Portfolio
{
    /**
     * @var PortfolioTransaction[]
     */
    public array $portfolioTransactions = [];

    /**
     * @var Transaction[]
     */
    public array $accountTransactions = [];
}
