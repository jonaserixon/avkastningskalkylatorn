<?php

class Presenter
{
    /**
     * @param TransactionSummary[] $summaries
     * @param array $currentSharePrices
     */
    public function presentResult(array $summaries, array $currentSharePrices): void
    {
        if (empty($summaries)) {
            echo 'No transaction file in csv format in the imports directory.' . PHP_EOL;
            return;
        }

        usort($summaries, function($a, $b) {
            return strcmp($a->name, $b->name);
        });

        echo '**********************************' . PHP_EOL;
        foreach ($summaries as $summary) {
            $currentPricePerShare = $currentSharePrices[$summary->isin] ?? null;
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

        echo "\n------ ". $summary->name ." (".$summary->isin.") ------\n";
        echo "Köpbelopp: \t\t\t\t" . number_format($summary->buyAmountTotal, 2) . " SEK\n";
        echo "Säljbelopp: \t\t\t" . number_format($summary->sellAmountTotal, 2) . " SEK\n";
        echo "Utdelningar: \t\t\t" . number_format($summary->dividendAmountTotal, 2) . " SEK\n";
        echo "\n";
        echo "Tot. avgifter: \t\t\t" . number_format($summary->feeAmountTotal, 2) . " SEK\n";
        echo "Köpavgifter: \t\t\t" . number_format($summary->feeBuyAmountTotal, 2) . " SEK\n";
        echo "Säljavgifter: \t\t\t" . number_format($summary->feeSellAmountTotal, 2) . " SEK\n";
        echo "\n";

        if ($summary->currentNumberOfShares > 0) {
            if ($currentValueOfShares) {
                echo "Nuvarande antal aktier: \t\t" . $summary->currentNumberOfShares . " st\n";
                echo "Nuvarande pris per aktie: \t\t" . number_format($currentPricePerShare, 2) . " SEK\n";
                echo "Nuvarande marknadsvärde för aktier: " . number_format($currentValueOfShares, 2) . " SEK\n";
            } else {
                echo "** Lägg in aktiens nuvarande pris för att beräkna avkastning etc. **.\n";
            }

            echo "\n";
        }

        $returns = $this->calculateReturns($summary, $currentValueOfShares);

        echo "Tot. avkastning: \t\t\t" . number_format($returns->totalReturnExclFees, 2) . " SEK\n";
        echo "Tot. avkastning: \t\t\t" . $returns->totalReturnExclFeesPercent . "%\n";

        echo "Tot. avkastning (m. avgifter): \t" . number_format($returns->totalReturnInclFees, 2) . " SEK\n";
        echo "Tot. avkastning (m. avgifter): \t" . $returns->totalReturnInclFeesPercent . "%\n";
        echo "\n";

        echo "----------------------------------------\n";
    }

    private function calculateReturns(TransactionSummary $summary, ?float $currentValueOfShares): stdClass
    {
        if ($currentValueOfShares === null) {
            $currentValueOfShares = 0;
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
