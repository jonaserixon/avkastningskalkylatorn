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

        $result = $profitCalculator->calculate();

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
                'amount' => $transaction->amount,
                'name' => $transaction->name,
                'type' => $transaction->type
            ];


            fputcsv($f, array_values($row), ',');
        }

        
        // exit;
        */

        ob_start();

        if ($options->verbose) {
            $this->presenter->displayDetailedSummaries($result->summaries);
        } else {
            $this->presenter->generateSummaryTable($result->summaries);
        }

        $currentBalance = $result->overview->calculateBalance($result->overview->cashFlows) - $result->overview->totalCurrentHoldings;
        echo 'Saldo: ' . $this->presenter->colorPicker($currentBalance) . ' SEK' . PHP_EOL;
        echo 'Saldo 2: ' . $this->presenter->colorPicker($result->overview->calculateBalance($result->overview->cashFlows)) . ' SEK' . PHP_EOL;

        print_r($result->overview->currentHoldingsWeighting);

        // TODO: Move this somewhere suitable (Presenter?)
        echo 'Tot. courtage: ' . $this->presenter->redText($result->overview->totalBuyCommission + $result->overview->totalSellCommission) . ' SEK' . PHP_EOL;
        echo 'Tot. köp-courtage: ' . $this->presenter->redText($result->overview->totalBuyCommission) . ' SEK' . PHP_EOL;
        echo 'Tot. sälj-courtage: ' . $this->presenter->redText($result->overview->totalSellCommission) . ' SEK' . PHP_EOL;
        echo 'Tot. avgifter: ' . $this->presenter->redText($result->overview->totalFee) . ' SEK' . PHP_EOL;
        echo 'Tot. skatt: ' . $this->presenter->redText($result->overview->totalTax) . ' SEK' . PHP_EOL;
        echo 'Tot. utländsk källskatt: ' . $this->presenter->redText($result->overview->totalForeignWithholdingTax) . ' SEK' . PHP_EOL;
        echo PHP_EOL;
        echo 'Tot. återbetald utländsk källskatt: ' . $this->presenter->colorPicker($result->overview->totalReturnedForeignWithholdingTax) . ' SEK' . PHP_EOL;
        echo 'Tot. utdelningar: ' . $this->presenter->colorPicker($result->overview->totalDividend) . ' SEK' . PHP_EOL;
        echo 'Tot. ränta: ' . $this->presenter->colorPicker($result->overview->totalInterest) . ' SEK' . PHP_EOL;
        echo PHP_EOL;
        echo 'Tot. köpbelopp: ' . $this->presenter->colorPicker($result->overview->totalBuyAmount) . ' SEK' . PHP_EOL;
        echo 'Tot. säljbelopp: ' . $this->presenter->colorPicker($result->overview->totalSellAmount) . ' SEK' . PHP_EOL;
        echo PHP_EOL;
        echo 'Tot. insättningar: ' . $this->presenter->colorPicker($result->overview->depositAmountTotal) . ' SEK' . PHP_EOL;
        echo 'Tot. uttag: ' . $this->presenter->colorPicker($result->overview->withdrawalAmountTotal) . ' SEK' . PHP_EOL;
        echo PHP_EOL;
        echo 'Tot. nuvarande innehav: ' . $this->presenter->colorPicker($result->overview->totalCurrentHoldings) . ' SEK' . PHP_EOL;
        echo PHP_EOL;
        echo 'Tot. avkastning: ' . $this->presenter->colorPicker($result->overview->totalProfitInclFees) . ' SEK' . PHP_EOL;
        echo 'Tot. avkastning: ' . $this->presenter->colorPicker($result->overview->returns->totalReturnInclFeesPercent) . '%' . PHP_EOL;

        echo PHP_EOL;
        echo 'XIRR: ' . $this->presenter->colorPicker($result->xirr * 100) . '%' . PHP_EOL;
        echo 'TWR: ' . $this->presenter->colorPicker($result->twr) . '%' . PHP_EOL;
        echo PHP_EOL;

        foreach ($result->currentHoldingsMissingPricePerShare as $companyMissingPrice) {
            echo $this->presenter->blueText('Info: Kurspris saknas för ' . $companyMissingPrice) . PHP_EOL;
        }

        ob_end_flush();
    }
}
