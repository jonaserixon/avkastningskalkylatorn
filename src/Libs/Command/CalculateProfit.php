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
        $asset = $this->options['asset'] ?? null;
        $dateFrom = $this->options['date-from'] ?? null;
        $dateTo = $this->options['date-to'] ?? null;

        $this->profitCalculator = new ProfitCalculator($exportCsv, $verbose, $bank, $isin, $asset, $dateFrom, $dateTo);
    }

    public function execute(): void
    {
        if (isset($this->options['current-holdings'])) {
            $this->profitCalculator->calculateCurrentHoldings();
            return;
        }

        $this->profitCalculator->init();
    }
}
