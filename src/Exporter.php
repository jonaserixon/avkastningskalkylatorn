<?php

class Exporter
{
    public static function generateCsvExport(array $summaries, array $currentSharePrices): void
    {
        usort($summaries, function($a, $b) {
            return strcmp($a->name, $b->name);
        });

        $filePath = "/exports/export_".date('Y-m-d_His').".csv";
        $csvHeaders = ['date', 'name', 'isin', 'buyAmountTotal', 'sellAmountTotal', 'dividendAmountTotal', 'feeAmountTotal', 'currentNumberOfShares', 'currentPricePerShare', 'currentValueOfShares', 'totalProfit'];
        $f = fopen($filePath, "w");
        fputcsv($f, $csvHeaders, ',');

        foreach ($summaries as $summary) {
            $currentPricePerShare = $currentSharePrices[$summary->isin] ?? null;
            $currentValueOfShares = null;
            if ($currentPricePerShare) {
                $currentValueOfShares = $summary->currentNumberOfShares * $currentPricePerShare;
            }

            $totalProfit = ($summary->sellAmountTotal + $summary->dividendAmountTotal + $currentValueOfShares) - ($summary->buyAmountTotal + $summary->feeAmountTotal);

            $row = [
                'date' => date('Y-m-d'),
                'name' => $summary->name,
                'isin' => $summary->isin,
                'buyAmountTotal' => $summary->buyAmountTotal,
                'sellAmountTotal' => $summary->sellAmountTotal,
                'dividendAmountTotal' => $summary->dividendAmountTotal,
                'feeAmountTotal' => $summary->feeAmountTotal,
                'currentNumberOfShares' => $summary->currentNumberOfShares,
                'currentPricePerShare' => $currentPricePerShare,
                'currentValueOfShares' => $currentValueOfShares,
                'totalProfit' => $totalProfit,
            ];

            fputcsv($f, array_values($row), ',');
        }
    }
}