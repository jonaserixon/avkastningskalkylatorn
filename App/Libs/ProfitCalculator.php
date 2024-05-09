<?php

namespace App\Libs;

// require_once 'DataStructure/Transaction.php';
// require_once 'DataStructure/TransactionSummary.php';
// require_once 'Enum/TransactionType.php';
// require_once 'Enum/Bank.php';
// require_once 'Presenter.php';
// require_once 'Importer.php';
// require_once 'Exporter.php';
// require_once 'TransactionHandler.php';

use App\DataStructure\TransactionSummary;
use App\Libs\Exporter;
use App\Libs\Importer;
use App\Libs\Presenter;
use App\Libs\TransactionHandler;
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

    public function init()
    {
        $generateCsv = getenv('GENERATE_CSV') === 'yes' ? true : false;

        $presenter = new Presenter();

        $importer = new Importer();
        $bankTransactions = $importer->parseBankTransactions();
    
        $transactionHandler = new TransactionHandler($presenter);
        $summaries = $transactionHandler->getTransactionsOverview($bankTransactions);

        if ($generateCsv) {
            Exporter::generateCsvExport($summaries, static::CURRENT_SHARE_PRICES);
        }

        $this->presentResult($presenter, $summaries, static::CURRENT_SHARE_PRICES);
    }

    /**
     * @param TransactionSummary[] $summaries
     */
    protected function presentResult(Presenter $presenter, array $summaries, array $currentSharePrices): void
    {
        echo $presenter->createSeparator('-') . PHP_EOL;

        $currentHoldingsMissingPricePerShare = [];
        foreach ($summaries as $summary) {
            $currentPricePerShare = $currentSharePrices[$summary->isin] ?? null;

            $currentValueOfShares = null;
            if ($currentPricePerShare && $summary->currentNumberOfShares > 0) {
                $currentValueOfShares = $summary->currentNumberOfShares * $currentPricePerShare;
            }

            $calculatedReturns = $this->calculateReturns($summary, $currentValueOfShares);
            $presenter->displayFormattedSummary($summary, $currentPricePerShare, $currentValueOfShares, $currentHoldingsMissingPricePerShare, $calculatedReturns);
        }

        echo PHP_EOL . $presenter->createSeparator('*') . PHP_EOL;
        echo PHP_EOL;

        foreach ($currentHoldingsMissingPricePerShare as $companyMissingPrice) {
            echo $presenter->blueText('Info: Kurspris saknas för ' . $companyMissingPrice) . PHP_EOL;
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

// try {
//     (new ProfitCalculator())->init();
// } catch (Exception $e) {
//     echo 'Error: ' . $e->getMessage();
// }
