<?php

namespace src\Libs\Command;

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
        $commandOptions = $this->commands['transaction']['options'];

        $options = new stdClass();
        $options->verbose = $this->options['verbose'] ?? $commandOptions['verbose']['default'];
        // $options->exportCsv = $this->options['export-csv'] ?? $commandOptions['export-csv']['default'];
        $options->bank = $this->options['bank'] ?? null;
        $options->isin = $this->options['isin'] ?? null;
        $options->asset = $this->options['asset'] ?? null;
        $options->dateFrom = $this->options['date-from'] ?? null;
        $options->dateTo = $this->options['date-to'] ?? null;
        $options->currentHoldings = $this->options['current-holdings'] ?? $commandOptions['current-holdings']['default'];
        $options->cashFlow = $this->options['cash-flow'] ?? null;

        return $options;
    }

    public function execute(): void
    {
        $options = $this->getParsedOptions();

        $profitCalculator = new ProfitCalculator(
            // $options->exportCsv,
            $options->bank,
            $options->isin,
            $options->asset,
            $options->dateFrom,
            $options->dateTo,
            $options->currentHoldings
        );

        $result = $profitCalculator->calculate();

        if ($options->cashFlow) {
            foreach ($result->overview->cashFlows as $cashFlow) {
                $res = $cashFlow->date . ' | ';
                $res .= $this->presenter->cyanText($cashFlow->rawAmount) . ' | ';
                $res .= $this->presenter->yellowText($cashFlow->type) . ' | ';
                $res .= $this->presenter->pinkText($cashFlow->name) . ' | ';
                $res .= $this->presenter->greenText($cashFlow->account) . ' | ';
                $res .= $this->presenter->greyText($cashFlow->bank);

                echo $res . PHP_EOL;
            }
            return;
        }

        // foreach ($transactions as $transaction) {
        //     echo $transaction->date . ' ' . $transaction->amount . ' ' . $transaction->type . ' ' . $transaction->name . PHP_EOL;
        // }
    }
}
