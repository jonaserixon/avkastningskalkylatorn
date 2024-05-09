<?php

class Presenter
{
    public const HYPHEN_LINE_SEPARATOR = '----------------------------------------';
    public const STAR_LINE_SEPARATOR = '****************************************';

    public function displayFormattedSummary(
        TransactionSummary $summary,
        ?float $currentPricePerShare,
        ?float $currentValueOfShares,
        array &$currentHoldingsMissingPricePerShare,
        ?stdClass $calculatedReturns
    ): void {
        echo "\n------ ". $summary->name ." (".$summary->isin.") ------\n";
        echo "Köpbelopp: \t\t\t\t" . number_format($summary->buyAmountTotal, 2, '.', ' ') . " SEK\n";
        echo "Säljbelopp: \t\t\t" . number_format($summary->sellAmountTotal, 2, '.', ' ') . " SEK\n";
        echo "Utdelningar: \t\t\t" . $this->colorPicker($summary->dividendAmountTotal) . " SEK\n";
        echo PHP_EOL;
        echo "Tot. avgifter: \t\t\t" . $this->redText($summary->feeAmountTotal) . " SEK\n";
        echo "Köpavgifter: \t\t\t" . $this->redText($summary->feeBuyAmountTotal) . " SEK\n";
        echo "Säljavgifter: \t\t\t" . $this->redText($summary->feeSellAmountTotal) . " SEK\n";
        echo PHP_EOL;

        if ($summary->currentNumberOfShares > 0) {
            if ($currentValueOfShares) {
                echo "Nuvarande antal aktier: \t\t" . $summary->currentNumberOfShares . " st\n";
                echo "Nuvarande pris/aktie: \t\t" . number_format($currentPricePerShare, 2, '.', ' ') . " SEK\n";
                echo "Nuvarande markn.värde av aktier: \t" . number_format($currentValueOfShares, 2, '.', ' ') . " SEK \n";
            } else {
                $currentHoldingsMissingPricePerShare[] = $summary->name . ' (' . $summary->isin . ')';
                echo "** Lägg in aktiens nuvarande pris för att beräkna avkastning etc. **.\n";
            }

            echo PHP_EOL;
        }

        if ($calculatedReturns) {
            echo "Tot. avkastning: \t\t\t" . $this->colorPicker($calculatedReturns->totalReturnExclFees) . " SEK\n";
            echo "Tot. avkastning: \t\t\t" . $this->colorPicker($calculatedReturns->totalReturnExclFeesPercent) . " %\n";
       
            echo "Tot. avkastning (m. avgifter): \t" . $this->colorPicker($calculatedReturns->totalReturnInclFees) . " SEK\n";
            echo "Tot. avkastning (m. avgifter): \t" . $this->colorPicker($calculatedReturns->totalReturnInclFeesPercent) . " %\n";
        }

        echo PHP_EOL;
        echo static::HYPHEN_LINE_SEPARATOR . PHP_EOL;
    }

    public function colorPicker(float $value): string
    {
        if ($value == 0) {
            return number_format($value, 2, '.', ' ');
        }
        return $value > 0 ? $this->greenText(number_format($value, 2, '.', ' ')) : $this->redText(number_format($value, 2, '.', ' '));
    }

    public function greenText(string $text): string
    {
        return "\033[32m" . $text . "\033[0m";
    }
    
    public function redText(string $text): string
    {
        return "\033[31m" . $text . "\033[0m";
    }

    public function blueText(string $text): string
    {
        return "\033[1;34m" . $text . "\033[0m";
    }
}
