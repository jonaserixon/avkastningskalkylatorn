<?php

class Presenter
{
    /**
     * @param TransactionSummary[] $summaries
     * @param float[] $currentSharePrices
     */
    public function presentResult(array $summaries, array $currentSharePrices): void
    {
        echo '**********************************' . PHP_EOL;
        foreach ($summaries as $summary) {
            $currentPricePerShare = $currentSharePrices[$summary->name] ?? null;
            $this->displayFormattedSummary($summary, $currentPricePerShare);
        }
        echo PHP_EOL . '**********************************' . PHP_EOL;
    }

    private function displayFormattedSummary(TransactionSummary $summary, ?float $currentPricePerShare): void
    {
        $currentValueOfShares = null;
        if ($currentPricePerShare) {
            $currentValueOfShares = $summary->currentNumberOfShares * $currentPricePerShare;
        }

        $totalProfit = ($summary->sellAmountTotal + $summary->dividendAmountTotal + $currentValueOfShares) - ($summary->buyAmountTotal + $summary->feeAmountTotal);

        echo "\n------ ". $summary->name ." ------\n";
        echo "Köpbelopp: " . number_format($summary->buyAmountTotal, 2) . " SEK\n";
        echo "Säljbelopp: " . number_format($summary->sellAmountTotal, 2) . " SEK\n";
        echo "Utdelningar: " . number_format($summary->dividendAmountTotal, 2) . " SEK\n";
        echo "Avgifter: " . number_format($summary->feeAmountTotal, 2) . " SEK\n";

        if ($currentValueOfShares) {
            echo "Nuvarande antal aktier: " . $summary->currentNumberOfShares . " st\n";
            echo "Nuvarande pris per aktie: " . number_format($currentPricePerShare, 2) . " SEK\n";
            echo "Nuvarande marknadsvärde för aktier: " . number_format($currentValueOfShares, 2) . " SEK\n";
        }

        echo "Total vinst/förlust: " . number_format($totalProfit, 2) . " SEK\n";
        echo "----------------------------------------\n";
    }
}
