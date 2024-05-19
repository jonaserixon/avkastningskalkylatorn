<?php

namespace Src\Libs\Command;

use Src\Libs\CommandProcessor;
use src\Libs\ProfitCalculator;
use stdClass;

class CalculateProfit extends CommandProcessor
{
    private array $options;
    private ProfitCalculator $profitCalculator;

    public function __construct(array $options)
    {
        $this->options = $options;

        parent::__construct();
    }

    public function getParsedOptions(): stdClass
    {
        $commandOptions = static::COMMANDS['calculate-profit']['options'];

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

        $this->profitCalculator = new ProfitCalculator(
            $options->exportCsv,
            $options->verbose,
            $options->bank,
            $options->isin,
            $options->asset,
            $options->dateFrom,
            $options->dateTo,
            $options->currentHoldings
        );

        $result = $this->profitCalculator->calculate();

        foreach ($result->summaries as $summary) {
            if ($options->verbose) {
                $this->presenter->displayVerboseFormattedSummary($summary, $summary->currentPricePerShare, $summary->currentValueOfShares);
            } else {
                $this->presenter->displayCompactFormattedSummary($summary);
            }
        }

        // TODO: Move this somewhere suitable.
        echo 'Tot. avgifter: ' . $this->presenter->colorPicker($result->overview->totalFee) . ' SEK' . PHP_EOL;
        echo 'Tot. utdelningar: ' . $this->presenter->colorPicker($result->overview->totalDividend) . ' SEK' . PHP_EOL;
        echo 'Tot. köpbelopp: ' . $this->presenter->colorPicker($result->overview->totalBuyAmount) . ' SEK' . PHP_EOL;
        echo 'Tot. säljbelopp: ' . $this->presenter->colorPicker($result->overview->totalSellAmount) . ' SEK' . PHP_EOL;
        echo 'Tot. nuvarande innehav: ' . $this->presenter->colorPicker($result->overview->totalCurrentHoldings) . ' SEK' . PHP_EOL;
        echo 'Tot. avkastning: ' . $this->presenter->colorPicker($result->overview->totalProfitInclFees) . ' SEK' . PHP_EOL;
        echo 'XIRR: ' . $this->presenter->colorPicker($result->xirr * 100) . '%' . PHP_EOL;

        foreach ($result->currentHoldingsMissingPricePerShare as $companyMissingPrice) {
            echo $this->presenter->blueText('Info: Kurspris saknas för ' . $companyMissingPrice) . PHP_EOL;
        }
    }
}
