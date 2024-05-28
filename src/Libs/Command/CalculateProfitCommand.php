<?php

namespace src\Libs\Command;

use src\Libs\ProfitCalculator;
use src\Libs\Transaction\TransactionLoader;
use stdClass;

class CalculateProfitCommand extends CommandProcessor
{
    /** @var mixed[] */
    private array $options;

    /**
     * @param mixed[] $options
     */
    public function __construct(array $options)
    {
        $this->options = $options;

        parent::__construct();
    }

    public function getParsedOptions(): stdClass
    {
        $commandOptions = $this->commands['calculate']['options'];

        $options = new stdClass();
        $options->verbose = $this->options['verbose'] ?? $commandOptions['verbose']['default'];
        // $options->exportCsv = $this->options['export-csv'] ?? $commandOptions['export-csv']['default'];
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

        $transactionLoader = new TransactionLoader(
            $options->bank,
            $options->isin,
            $options->asset,
            $options->dateFrom,
            $options->dateTo,
            $options->currentHoldings
        );

        $assets = $transactionLoader->getFinancialAssets($transactionLoader->getTransactions());
        $profitCalculator = new ProfitCalculator($this->presenter, $options->currentHoldings);
        $result = $profitCalculator->calculate($assets, $transactionLoader->overview);

        if ($options->verbose) {
            $this->presenter->displayDetailedAssets($result->assets);
        } else {
            $this->presenter->generateAssetTable($result->overview, $result->assets);
        }

        // $this->presenter->displayFinancialOverview($result->overview);

        if (!empty($result->overview->currentHoldingsWeighting)) {
            echo PHP_EOL . $this->presenter->pinkText('Portföljviktning: ') . PHP_EOL. PHP_EOL;
            $maxValue = max(array_values($result->overview->currentHoldingsWeighting));
            foreach ($result->overview->currentHoldingsWeighting as $isin => $weight) {
                $this->presenter->printRelativeProgressBar($isin, $weight, $maxValue);
            }
        }

        $this->presenter->displayInvestmentReport($result->overview, $result->assets);

        foreach ($result->currentHoldingsMissingPricePerShare as $companyMissingPrice) {
            echo $this->presenter->blueText('Info: Kurspris saknas för ' . $companyMissingPrice) . PHP_EOL;
        }

        $this->presenter->displayAssetNotices($result->assets);

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
