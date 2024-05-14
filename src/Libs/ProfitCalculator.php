<?php

namespace src\Libs;

use src\DataStructure\TransactionSummary;
use src\Libs\FileManager\StockPriceManager;
use src\Libs\FileManager\Exporter;
use src\Libs\FileManager\Importer;
use stdClass;

class ProfitCalculator
{
    private bool $generateCsv;
    private Presenter $presenter;

    public function __construct(bool $generateCsv = false)
    {
        $this->generateCsv = $generateCsv;
        $this->presenter = new Presenter();
    }

    public function init()
    {
        $stockPriceManager = new StockPriceManager();
        $importer = new Importer();

        $bankTransactions = $importer->parseBankTransactions();
    
        $transactionHandler = new TransactionHandler($this->presenter);
        $summaries = $transactionHandler->getTransactionsOverview($bankTransactions);

        if ($this->generateCsv) {
            Exporter::generateCsvExport($summaries, $stockPriceManager);
        }

        $this->presentResult($summaries, $stockPriceManager);
    }

    /**
     * @param TransactionSummary[] $summaries
     */
    protected function presentResult(array $summaries, StockPriceManager $stockPriceManager): void
    {
        echo $this->presenter->createSeparator('-') . PHP_EOL;

        $currentHoldingsMissingPricePerShare = [];
        foreach ($summaries as $summary) {
            $currentPricePerShare = $stockPriceManager->getCurrentPriceByIsin($summary->isin);

            $currentValueOfShares = null;
            if ($currentPricePerShare && $summary->currentNumberOfShares > 0) {
                // TODO: move calculations to a separate method
                $currentValueOfShares = $summary->currentNumberOfShares * $currentPricePerShare;
            }

            $calculatedReturns = $this->calculateReturns($summary, $currentValueOfShares);
            $this->presenter->displayFormattedSummary($summary, $currentPricePerShare, $currentValueOfShares, $currentHoldingsMissingPricePerShare, $calculatedReturns);
        }

        echo PHP_EOL . $this->presenter->createSeparator('*') . PHP_EOL;
        echo PHP_EOL;

        foreach ($currentHoldingsMissingPricePerShare as $companyMissingPrice) {
            echo $this->presenter->blueText('Info: Kurspris saknas för ' . $companyMissingPrice) . PHP_EOL;
        }

        echo PHP_EOL;
    }

    public function calculateReturns(TransactionSummary $summary, ?float $currentValueOfShares): ?stdClass
    {
        if ($currentValueOfShares === null) {
            $currentValueOfShares = 0;
        }

        if ($summary->buyAmountTotal <= 0) {
            return null;
        }

        $adjustedTotalBuyAmount = $summary->buyAmountTotal + $summary->feeBuyAmountTotal;
        $adjustedTotalSellAmount = $summary->sellAmountTotal + $summary->dividendAmountTotal - $summary->feeSellAmountTotal;

        // Beräkna total avkastning exklusive avgifter
        $totalReturnExclFees = $summary->sellAmountTotal + $summary->dividendAmountTotal + $currentValueOfShares - $summary->buyAmountTotal;

        $totalReturnExclFeesPercent = round($totalReturnExclFees / $summary->buyAmountTotal * 100, 2);

        // Beräkna total avkastning inklusive avgifter
        $totalReturnInclFees = $adjustedTotalSellAmount + $currentValueOfShares - $adjustedTotalBuyAmount;
        $totalReturnInclFeesPercent = round($totalReturnInclFees / $adjustedTotalBuyAmount * 100, 2);

        $result = new stdClass();
        $result->totalReturnExclFees = $totalReturnExclFees;
        $result->totalReturnExclFeesPercent = $totalReturnExclFeesPercent;
        $result->totalReturnInclFees = $totalReturnInclFees;
        $result->totalReturnInclFeesPercent = $totalReturnInclFeesPercent;

        return $result;
    }
}
