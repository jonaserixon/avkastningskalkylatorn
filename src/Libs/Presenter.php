<?php

namespace src\Libs;

use src\DataStructure\TransactionSummary;
use stdClass;

class Presenter
{
    public function displayFormattedSummary(
        TransactionSummary $summary,
        ?float $currentPricePerShare,
        ?float $currentValueOfShares,
        array &$currentHoldingsMissingPricePerShare,
        ?stdClass $calculatedReturns
    ): void {
        echo PHP_EOL;

        echo $this->pinkText($this->createSeparator('-', $summary->name .' ('.$summary->isin.')')) . PHP_EOL;

        echo $this->addTabs('Köpbelopp:') . $this->cyanText(number_format($summary->buyAmountTotal, 2, '.', ' ')) . ' SEK' . PHP_EOL;
        echo $this->addTabs('Säljbelopp:') . $this->blueText(number_format($summary->sellAmountTotal, 2, '.', ' ')) . ' SEK' . PHP_EOL;
        echo $this->addTabs('Utdelningar:', 40) . $this->colorPicker($summary->dividendAmountTotal) . ' SEK' . PHP_EOL;

        echo PHP_EOL;

        echo $this->addTabs('Tot. avgifter:') . $this->redText($summary->feeAmountTotal) . ' SEK' . PHP_EOL;
        echo $this->addTabs('Köpavgifter:') . $this->redText($summary->feeBuyAmountTotal) . ' SEK' . PHP_EOL;
        echo $this->addTabs('Säljavgifter:') . $this->redText($summary->feeSellAmountTotal) . ' SEK' . PHP_EOL;

        echo PHP_EOL;

        if ((int) $summary->currentNumberOfShares > 0) {
            echo $this->addTabs('Nuvarande antal aktier:') . number_format($summary->currentNumberOfShares, 2, '.', ' ') . ' st' . PHP_EOL;

            if ($currentValueOfShares) {
                echo $this->addTabs('Nuvarande pris/aktie', 40) . number_format($currentPricePerShare, 2, '.', ' ') . ' SEK' . PHP_EOL;
                echo $this->addTabs('Nuvarande markn.värde av aktier:') . number_format($currentValueOfShares, 2, '.', ' ') . ' SEK ' . PHP_EOL;
            } else {
                $currentHoldingsMissingPricePerShare[] = $summary->name . ' (' . $summary->isin . ')';
                echo $this->yellowText('** Saknar kurspris **') . PHP_EOL;
            }

            echo PHP_EOL;
        }

        if ($calculatedReturns) {
            echo $this->addTabs('Tot. avkastning:') . $this->colorPicker($calculatedReturns->totalReturnExclFees) . ' SEK' . PHP_EOL;
            echo $this->addTabs('Tot. avkastning:') . $this->colorPicker($calculatedReturns->totalReturnExclFeesPercent) . ' %' . PHP_EOL;
       
            echo $this->addTabs('Tot. avkastning (m. avgifter):') . $this->colorPicker($calculatedReturns->totalReturnInclFees) . ' SEK' . PHP_EOL;
            echo $this->addTabs('Tot. avkastning (m. avgifter):') . $this->colorPicker($calculatedReturns->totalReturnInclFeesPercent) . ' %' . PHP_EOL;
        }

        echo PHP_EOL;
    }

    public function addTabs($label, $desiredColumnWidth = 45) {
        $currentLength = strlen($label);
        $spacesNeeded = $desiredColumnWidth - $currentLength;
    
        $tabsCount = ceil($spacesNeeded / 8);
    
        $tabsCount = max($tabsCount, 1);
    
        return $label . str_repeat("\t", $tabsCount);
    }

    public function createSeparator(string $character = '-', string $name = '', int $totalWidth = 60): string
    {
        $text = $name;
        $lineLength = $totalWidth - strlen($text);

        if ($lineLength > 0) {
            $halfLine = str_repeat($character, (int) floor($lineLength / 2));
            $line = $halfLine . $text . $halfLine;

            if ($lineLength % 2 == 1) {
                $line .= $character;
            }
        } else {
            $line = $text;
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

    public function pinkText($value): string
    {
        return "\033[38;5;213m" . $value . "\033[0m";
    }

    public function greenText($value): string
    {
        return "\033[32m" . $value . "\033[0m";
    }
    
    public function redText($value): string
    {
        return "\033[31m" . $value . "\033[0m";
    }

    public function blueText($value): string
    {
        return "\033[1;34m" . $value . "\033[0m";
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
