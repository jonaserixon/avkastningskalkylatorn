<?php

namespace src\Command;

use src\Command\CommandProcessor;
use src\Service\ProfitCalculator;
use src\Service\Transaction\TransactionLoader;
use src\View\Logger;
use src\View\TextColorizer;
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
        $options->overview = $this->options['overview'] ?? $commandOptions['overview']['default'];
        $options->account = $this->options['account'] ?? null;
        $options->displayLog = $this->options['display-log'] ?? $commandOptions['display-log']['default'];

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
            $options->currentHoldings,
            $options->account
        );

        $assets = $transactionLoader->getFinancialAssets($transactionLoader->getTransactions());
        $profitCalculator = new ProfitCalculator($options->currentHoldings);
        $result = $profitCalculator->calculate($assets, $transactionLoader->overview);

        if ($options->verbose) {
            $this->presenter->displayDetailedAssets($result->assets);
        } else {
            $this->presenter->generateAssetTable($result->overview, $result->assets);
        }

        // $this->presenter->displayFinancialOverview($result->overview);

        if (!empty($result->overview->currentHoldingsWeighting)) {
            $weightings = array_values($result->overview->currentHoldingsWeighting);

            if (!empty($weightings)) {
                echo PHP_EOL . TextColorizer::backgroundColor('Portföljviktning: ', 'pink', 'black') . PHP_EOL. PHP_EOL;

                $maxValue = max($weightings);
                foreach ($result->overview->currentHoldingsWeighting as $isin => $weight) {
                    $this->presenter->printRelativeProgressBar($isin, $weight, $maxValue);
                }
            }
        }

        if ($options->overview) {
            $this->presenter->displayInvestmentReport($result->overview, $result->assets);
        }

        foreach ($result->currentHoldingsMissingPricePerShare as $companyMissingPrice) {
            Logger::getInstance()->addInfo('Kurspris saknas för ' . $companyMissingPrice);
        }

        $this->presenter->displayAssetNotices($result->assets);

        Logger::getInstance()->printInfos();

        if ($options->displayLog) {
            Logger::getInstance()
                ->printNotices()
                ->printWarnings();
        }
    }
}
