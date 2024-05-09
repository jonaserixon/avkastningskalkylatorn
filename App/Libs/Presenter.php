<?php

namespace App\Libs;

use App\DataStructure\TransactionSummary;
use stdClass;

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
        echo PHP_EOL;

        echo $this->pinkText($this->createSeparator('-', $summary->name ." (".$summary->isin.")")) . PHP_EOL;

        echo "Köpbelopp: \t\t\t\t\t" . $this->cyanText(number_format($summary->buyAmountTotal, 2, '.', ' ')) . " SEK" . PHP_EOL;
        echo "Säljbelopp: \t\t\t\t" . $this->blueText(number_format($summary->sellAmountTotal, 2, '.', ' ')) . " SEK" . PHP_EOL;
        echo "Utdelningar: \t\t\t\t" . $this->colorPicker($summary->dividendAmountTotal) . " SEK" . PHP_EOL;

        echo PHP_EOL;

        echo "Tot. avgifter: \t\t\t\t" . $this->redText($summary->feeAmountTotal) . " SEK" . PHP_EOL;
        echo "Köpavgifter: \t\t\t\t" . $this->redText($summary->feeBuyAmountTotal) . " SEK" . PHP_EOL;
        echo "Säljavgifter: \t\t\t\t" . $this->redText($summary->feeSellAmountTotal) . " SEK" . PHP_EOL;

        echo PHP_EOL;

        if ($summary->currentNumberOfShares > 0) {
            echo "Nuvarande antal aktier: \t\t\t" . $summary->currentNumberOfShares . " st" . PHP_EOL;

            if ($currentValueOfShares) {
                echo "Nuvarande pris/aktie: \t\t\t" . number_format($currentPricePerShare, 2, '.', ' ') . " SEK" . PHP_EOL;
                echo "Nuvarande markn.värde av aktier: \t\t" . number_format($currentValueOfShares, 2, '.', ' ') . " SEK " . PHP_EOL;
            } else {
                $currentHoldingsMissingPricePerShare[] = $summary->name . ' (' . $summary->isin . ')';
                echo $this->yellowText("** Saknar kurspris **") . PHP_EOL;
            }

            echo PHP_EOL;
        }

        if ($calculatedReturns) {
            echo "Tot. avkastning: \t\t\t\t" . $this->colorPicker($calculatedReturns->totalReturnExclFees) . " SEK" . PHP_EOL;
            echo "Tot. avkastning: \t\t\t\t" . $this->colorPicker($calculatedReturns->totalReturnExclFeesPercent) . " %" . PHP_EOL;
       
            echo "Tot. avkastning (m. avgifter): \t\t" . $this->colorPicker($calculatedReturns->totalReturnInclFees) . " SEK" . PHP_EOL;
            echo "Tot. avkastning (m. avgifter): \t\t" . $this->colorPicker($calculatedReturns->totalReturnInclFeesPercent) . " %" . PHP_EOL;
        }

        echo PHP_EOL;
    }


    public function createSeparator(string $character = '-', string $name = '', int $totalWidth = 60): string
    {
        $text = $name;
        $lineLength = $totalWidth - strlen($text);

        if ($lineLength > 0) {
            $halfLine = str_repeat($character, floor($lineLength / 2));
            $line = $halfLine . $text . $halfLine;

            // Lägg till ett extra bindestreck om det totala antalet behöver jämnas ut
            if ($lineLength % 2 == 1) {
                $line .= $character;
            }
        } else {
            $line = $text;  // Om det inte finns utrymme för bindestreck, visa bara texten
        }

        return $line;
    }

    public function colorPicker(float $value): string
    {
        if ($value == 0) {
            return number_format($value, 2, '.', ' ');
        }
        return $value > 0 ? $this->greenText(number_format($value, 2, '.', ' ')) : $this->redText(number_format($value, 2, '.', ' '));
    }

    public function pinkText(string $text): string
    {
        return "\033[38;5;213m" . $text . "\033[0m";
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

    public function cyanText(string $text): string
    {
        return "\033[36m" . $text . "\033[0m";
    }

    public function yellowText(string $text): string
    {
        return "\033[33m" . $text . "\033[0m";
    }
}
