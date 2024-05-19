<?php

namespace Src\Libs\Command;

use Src\Libs\CommandProcessor;
use src\Libs\ProfitCalculator;

class CalculateProfit extends CommandProcessor
{
    private array $options;
    private ProfitCalculator $profitCalculator;

    public function __construct(array $options)
    {
        $this->options = $options;

        $verbose = isset($this->options['verbose']) ?? static::COMMANDS['calculate-profit']['options']['verbose']['default'];
        $exportCsv = isset($this->options['export-csv']) ?? static::COMMANDS['calculate-profit']['options']['export-csv']['default'];
        $bank = $this->options['bank'] ?? null;
        $isin = $this->options['isin'] ?? null;

        $this->profitCalculator = new ProfitCalculator($exportCsv, $verbose, $bank, $isin);
    }

    public function execute(): void
    {
        if (isset($this->options['current-holdings'])) {
            $this->profitCalculator->calculateCurrentHoldings();
            return;
        }

        echo "Executing CalculateProfit command\n";
        $this->profitCalculator->init();
    }
}
