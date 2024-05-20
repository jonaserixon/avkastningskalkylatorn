<?php

namespace Src\Libs\Command;

use src\Libs\ProfitCalculator;
use stdClass;

class TransactionCommand extends CommandProcessor
{
    private array $options;

    public function __construct(array $options)
    {
        $this->options = $options;

        parent::__construct();
    }

    public function getParsedOptions(): stdClass
    {
        $commandOptions = $this->commands['calculate-profit']['options'];

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

        $transactions = $profitCalculator->getTransactions();

        foreach ($transactions as $transaction) {
            echo $transaction->date . ' ' . $transaction->amount . ' ' . $transaction->type . ' ' . $transaction->name . PHP_EOL;
        }
    }
}
