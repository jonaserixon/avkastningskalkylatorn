<?php

namespace App\Libs\FileManager;

use App\Libs\ProfitCalculator;

class Exporter
{
    public static function generateCsvExport(array $summaries, array $currentSharePrices): void
    {
        usort($summaries, function($a, $b) {
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
            $currentPricePerShare = $currentSharePrices[$summary->isin] ?? null;
            $currentValueOfShares = null;
            $currentValueOfShares = null;
            if ($currentPricePerShare && $summary->currentNumberOfShares > 0) {
                $currentValueOfShares = $summary->currentNumberOfShares * $currentPricePerShare;
            }

            $profitCalculator = new ProfitCalculator();
            $calculatedReturns = $profitCalculator->calculateReturns($summary, $currentValueOfShares);

            $row = [
                'date' => date('Y-m-d'),
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
}