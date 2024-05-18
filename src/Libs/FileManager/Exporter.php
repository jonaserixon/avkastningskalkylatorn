<?php

namespace src\Libs\FileManager;

use src\DataStructure\TransactionSummary;
use src\Libs\FileManager\Importer\StockPrice;
use src\Libs\ProfitCalculator;

class Exporter
{
    /**
     * @param TransactionSummary[] $summaries
     */
    public static function generateCsvExport(array $summaries, StockPrice $stockPrice): void
    {
        usort($summaries, function ($a, $b) {
            return strcmp($a->name, $b->name);
        });

        $filePath = "/exports/export_".date('Y-m-d_His').".csv";
        $csvHeaders = [
            'date',
            'name',
            'isin',
            'buyAmountTotal',
            'sellAmountTotal',
            'dividendAmountTotal',
            'feeAmountTotal',
            'feeSellAmountTotal',
            'feeBuyAmountTotal',
            'currentNumberOfShares',
            'currentPricePerShare',
            'currentValueOfShares',
            'totalReturnExclFees',
            'totalReturnExclFeesPercent',
            'totalReturnInclFees',
            'totalReturnInclFeesPercent'
        ];
        $f = fopen($filePath, "w");
        fputcsv($f, $csvHeaders, ',');

        foreach ($summaries as $summary) {
            $currentPricePerShare = $stockPrice->getCurrentPriceByIsin($summary->isin);

            $currentValueOfShares = null;
            if ($currentPricePerShare && $summary->currentNumberOfShares > 0) {
                $currentValueOfShares = $summary->currentNumberOfShares * $currentPricePerShare;
            }

            $profitCalculator = new ProfitCalculator();
            $calculatedReturns = $profitCalculator->calculateReturns($summary, $currentValueOfShares);

            if ($calculatedReturns === null) {
                continue;
            }

            $row = [
                // 'date' => date('Y-m-d'),
                'name' => $summary->name,
                'isin' => $summary->isin,
                'buyAmountTotal' => $summary->buyAmountTotal,
                'sellAmountTotal' => $summary->sellAmountTotal,
                'dividendAmountTotal' => $summary->dividendAmountTotal,
                'feeAmountTotal' => $summary->feeAmountTotal,
                'feeSellAmountTotal' => $summary->feeSellAmountTotal,
                'feeBuyAmountTotal' => $summary->feeBuyAmountTotal,
                'currentNumberOfShares' => $summary->currentNumberOfShares,
                'currentPricePerShare' => $currentPricePerShare,
                'currentValueOfShares' => $currentValueOfShares,
                'totalReturnExclFees' => $calculatedReturns->totalReturnExclFees,
                'totalReturnExclFeesPercent' => $calculatedReturns->totalReturnExclFeesPercent,
                'totalReturnInclFees' => $calculatedReturns->totalReturnInclFees,
                'totalReturnInclFeesPercent' => $calculatedReturns->totalReturnInclFeesPercent
            ];

            fputcsv($f, array_values($row), ',');
        }
    }

    public static function testGenerateCsvExport(array $transactions): void
    {
        $filePath = "/exports/export_".date('Y-m-d_His').".csv";
        $csvHeaders = [
            'date',
            'amount'
        ];
        $f = fopen($filePath, "w");
        fputcsv($f, $csvHeaders, ',');

        foreach ($transactions as $transaction) {
            $row = [
                'date' => $transaction->date,
                'amount' => $transaction->amount,
            ];

            fputcsv($f, array_values($row), ',');
        }
    }
}
