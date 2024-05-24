<?php

namespace Src\Libs\Command;

use src\Libs\ProfitCalculator;
use stdClass;

class CalculateProfitCommand extends CommandProcessor
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

        if ($options->verbose) {
            $this->presenter->displayDetailedSummaries($result->summaries);
        } else {
            $this->presenter->generateSummaryTable($result->summaries);
        }

        $this->presenter->displayOverview($result->overview);

        foreach ($result->currentHoldingsMissingPricePerShare as $companyMissingPrice) {
            echo $this->presenter->blueText('Info: Kurspris saknas för ' . $companyMissingPrice) . PHP_EOL;
        }

        /*
        $filePath = "/exports/export_".date('Y-m-d_His').".csv";
        $csvHeaders = [
            'date',
            'amount',
            'name',
            'type'
        ];
        $f = fopen($filePath, "w");
        fputcsv($f, $csvHeaders, ',');

        foreach ($result->overview->cashFlows as $transaction) {
            $row = [
                'date' => $transaction->date,
                'amount' => $transaction->rawAmount,
                'name' => $transaction->name,
                'type' => $transaction->type
            ];

            fputcsv($f, array_values($row), ',');
        }
        */
    }
}
