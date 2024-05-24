<?php

namespace Src\Libs\Command;

use src\Libs\FileManager\Exporter;
use src\Libs\ProfitCalculator;
use stdClass;

class GenerateIsinListCommand extends CommandProcessor
{
    private array $options;

    public function __construct(array $options)
    {
        $this->options = $options;

        parent::__construct();
    }

    public function getParsedOptions(): stdClass
    {
        $commandOptions = $this->commands['calculate']['options'];

        $options = new stdClass();
        $options->verbose = isset($this->options['verbose']) ?? $commandOptions['verbose']['default'];
        $options->exportCsv = isset($this->options['export-csv']) ?? $commandOptions['export-csv']['default'];
        $options->bank = $this->options['bank'] ?? null;
        $options->isin = $this->options['isin'] ?? null;
        $options->asset = $this->options['asset'] ?? null;
        $options->dateFrom = $this->options['date-from'] ?? null;
        $options->dateTo = $this->options['date-to'] ?? null;
        $options->currentHoldings = $this->options['current-holdings'] ?? $commandOptions['current-holdings']['default'];

        return $options;
    }

    public function execute(): void
    {
        $options = $this->getParsedOptions();

        $profitCalculator = new ProfitCalculator(
            $options->exportCsv,
            $options->verbose,
            $options->bank,
            $options->isin,
            $options->asset,
            $options->dateFrom,
            $options->dateTo,
            $options->currentHoldings
        );
        
        $result = $profitCalculator->calculate();

        $isinList = [];
        foreach ($result->assets as $asset) {
            $isinList[] = [
                'name' => $asset->name,
                'isin' => $asset->isin
            ];
        }

        Exporter::exportToCsv(['name', 'isin'], $isinList, 'isin_list');
    }
}
