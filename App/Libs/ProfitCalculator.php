<?php

namespace App\Libs;

use App\DataStructure\TransactionSummary;
use App\Libs\FileManager\Exporter;
use App\Libs\FileManager\Importer;
use stdClass;

class ProfitCalculator
{
    private const CURRENT_SHARE_PRICES = [
        'SE0020050417' => 356.50, // Boliden,
        'SE0017832488' => 70.34, // Balder,
        'US1104481072' => 328.1408, // BTI,
        'SE0012673267' => 1235.00, // Evolution,
        'NO0012470089' => 139.57427, // Tomra
        'US25243Q2057' => 1543.4232, // Diageo
        'US7181721090' => 1072.5336 // PM
    ];

    private bool $generateCsv;
    private Presenter $presenter;

    public function __construct(bool $generateCsv = false)
    {
        $this->generateCsv = $generateCsv;
        $this->presenter = new Presenter();
    }


    public function init()
    {
        $importer = new Importer();

        $bankTransactions = $importer->parseBankTransactions();
    
        $transactionHandler = new TransactionHandler($this->presenter);
        $summaries = $transactionHandler->getTransactionsOverview($bankTransactions);

        if ($this->generateCsv) {
            Exporter::generateCsvExport($summaries, static::CURRENT_SHARE_PRICES);
        }

        $this->presentResult($summaries, static::CURRENT_SHARE_PRICES);
    }

    /**
     * @param TransactionSummary[] $summaries
     */
    protected function presentResult(array $summaries, array $currentSharePrices): void
    {
        echo $this->presenter->createSeparator('-') . PHP_EOL;

        $currentHoldingsMissingPricePerShare = [];
        foreach ($summaries as $summary) {
            $currentPricePerShare = $currentSharePrices[$summary->isin] ?? null;

            $currentValueOfShares = null;
            if ($currentPricePerShare && $summary->currentNumberOfShares > 0) {
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
