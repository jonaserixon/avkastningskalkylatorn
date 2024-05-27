<?php

namespace src\Libs\Command;

use src\Libs\FileManager\Exporter;
use src\Libs\ProfitCalculator;
use src\Libs\Transaction\TransactionLoader;
use stdClass;

class GenerateIsinListCommand extends CommandProcessor
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
            // $this->exportCsv,
            $options->bank,
            $options->isin,
            $options->asset,
            $options->dateFrom,
            $options->dateTo,
            $options->currentHoldings
        );

        $assets = $transactionLoader->getFinancialAssets($transactionLoader->getTransactions());
        $profitCalculator = new ProfitCalculator($options->currentHoldings);
        $result = $profitCalculator->calculate($assets, $transactionLoader->overview);

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
