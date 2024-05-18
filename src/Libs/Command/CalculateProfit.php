<?php

namespace Src\Libs\Command;

use src\Libs\ProfitCalculator;

class CalculateProfit
{
    private array $options;
    private ProfitCalculator $profitCalculator;

    public function __construct(array $options)
    {
        $this->options = $options;
        $this->profitCalculator = new ProfitCalculator();
    }

    public function execute(): void
    {
        echo "Executing CalculateProfit command\n";
        $this->profitCalculator->init();
    }
}
