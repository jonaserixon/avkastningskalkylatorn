<?php

namespace src\Libs;

use src\DataStructure\TransactionSummary;

class Presenter
{
    private const TAB_SIZE = 4;

    public function displayDetailedSummaries(array $summaries): void
    {
        foreach ($summaries as $summary) {
            echo PHP_EOL;

            echo $this->pinkText($this->createSeparator('-', $summary->name .' ('.$summary->isin.')')) . PHP_EOL;

            echo $this->addTabs('Köpbelopp:') . $this->cyanText(number_format($summary->buyTotal, 2, '.', ' ')) . ' SEK' . PHP_EOL;
            echo $this->addTabs('Säljbelopp:') . $this->blueText(number_format($summary->sellTotal, 2, '.', ' ')) . ' SEK' . PHP_EOL;
            echo $this->addTabs('Utdelningar:') . $this->colorPicker($summary->dividendTotal) . ' SEK' . PHP_EOL;

            echo PHP_EOL;

            echo $this->addTabs('Tot. avgifter:', 50) . $this->redText($summary->commissionAmountTotal) . ' SEK' . PHP_EOL;
            echo $this->addTabs('Köpavgifter:', 50) . $this->redText($summary->commissionBuyAmountTotal) . ' SEK' . PHP_EOL;
            echo $this->addTabs('Säljavgifter:', 50) . $this->redText($summary->commissionSellAmountTotal) . ' SEK' . PHP_EOL;

            echo PHP_EOL;

            if ((int) $summary->currentNumberOfShares > 0) {
                echo $this->addTabs('Nuvarande antal aktier:', 50) . number_format($summary->currentNumberOfShares, 2, '.', ' ') . ' st' . PHP_EOL;

                if ($summary->currentValueOfShares) {
                    echo $this->addTabs('Nuvarande pris/aktie') . number_format($summary->currentPricePerShare, 2, '.', ' ') . ' SEK' . PHP_EOL;
                    echo $this->addTabs('Nuvarande markn.värde av aktier:') . number_format($summary->currentValueOfShares, 2, '.', ' ') . ' SEK ' . PHP_EOL;
                } else {
                    echo $this->yellowText('** Saknar kurspris **') . PHP_EOL;
                }

                echo PHP_EOL;
            }

            if ($summary->assetReturn) {
                echo $this->addTabs('Tot. avkastning:') . $this->colorPicker($summary->assetReturn->totalReturnExclFees) . ' SEK' . PHP_EOL;
                echo $this->addTabs('Tot. avkastning:') . $this->colorPicker($summary->assetReturn->totalReturnExclFeesPercent) . ' %' . PHP_EOL;

                echo $this->addTabs('Tot. avkastning (m. avgifter):', 50) . $this->colorPicker($summary->assetReturn->totalReturnInclFees) . ' SEK' . PHP_EOL;
                echo $this->addTabs('Tot. avkastning (m. avgifter):', 50) . $this->colorPicker($summary->assetReturn->totalReturnInclFeesPercent) . ' %' . PHP_EOL;
            }

            echo PHP_EOL;
        }
    }

    public function truncateName(string $name, int $maxLength): string
    {
        if (strlen($name) > $maxLength) {
            return substr($name, 0, $maxLength - 3) . '...';
        }

        return $name;
    }

    public function generateSummaryTable(array $summaries): void
    {
        $headers = ['Värdepapper', 'ISIN', 'Avkastning %', 'Avkastning SEK', 'Total utdelning', 'Totalt courtage'];

        $colWidths = array_fill(0, count($headers), 0);

        foreach ($headers as $colIndex => $header) {
            $colWidths[$colIndex] = mb_strlen($header);
        }

        foreach ($summaries as $summary) {
            if (!$summary->assetReturn) {
                continue;
            }

            $name = $summary->name;
            if (mb_strlen($name) > 40) {
                $name = $this->truncateName($name, 40);
            }

            $colWidths[0] = max($colWidths[0], mb_strlen($name));
            $colWidths[1] = max($colWidths[1], mb_strlen($summary->isin));
            $colWidths[2] = max($colWidths[2], mb_strlen($this->formatNumber($summary->assetReturn->totalReturnInclFeesPercent) . ' %'));
            $colWidths[3] = max($colWidths[3], mb_strlen($this->formatNumber($summary->assetReturn->totalReturnInclFees) . ' SEK'));
            $colWidths[4] = max($colWidths[4], mb_strlen($this->formatNumber($summary->dividendTotal) . ' SEK'));
            $colWidths[5] = max($colWidths[4], mb_strlen($this->formatNumber($summary->commissionAmountTotal) . ' SEK'));
        }

        $this->printHorizontalLine($colWidths);
        $this->printRow($headers, $colWidths);
        $this->printHorizontalLine($colWidths);

        foreach ($summaries as $summary) {
            if (!$summary->assetReturn) {
                continue;
            }
            $name = $summary->name;
            if (mb_strlen($name) > 40) {
                $name = $this->truncateName($name, 40);
            }
            $this->printRow([
                $name,
                $summary->isin,
                $this->formatNumber($summary->assetReturn->totalReturnInclFeesPercent) . ' %',
                $this->formatNumber($summary->assetReturn->totalReturnInclFees) . ' SEK',
                $this->formatNumber($summary->dividendTotal) . ' SEK',
                $this->formatNumber($summary->commissionAmountTotal) . ' SEK'
            ], $colWidths);
            $this->printHorizontalLine($colWidths);
        }
    }

    public function printHorizontalLine($colWidths)
    {
        foreach ($colWidths as $width) {
            echo '+' . str_repeat('-', $width + 2);
        }
        echo '+' . PHP_EOL;
    }

    public function printRow($row, $colWidths)
    {
        foreach ($row as $colIndex => $colValue) {
            $visibleLength = mb_strlen($colValue);
            $padding = $colWidths[$colIndex] - $visibleLength;
            printf("| %s%s ", $colValue, str_repeat(' ', $padding));
        }
        echo '|' . PHP_EOL;
    }

    public function displayCompactFormattedSummary(TransactionSummary $summary): void
    {
        if (!$summary->assetReturn) {
            return;
        }

        $assetName = $summary->name .' ('.$summary->isin.')';
        $assetNameTextLength = mb_strlen($assetName);

        // Calculate the number of spaces needed to align text
        $spaces = str_repeat(' ', (70 - $assetNameTextLength + self::TAB_SIZE) < 0 ? 0 : (70 - $assetNameTextLength + self::TAB_SIZE));

        $result = $this->pinkText($assetName);
        $result .= $spaces;
        $result .= $this->colorPicker($summary->assetReturn->totalReturnInclFeesPercent) . ' %';
        $result .= ' | ';
        $result .= $this->colorPicker($summary->assetReturn->totalReturnInclFees) . ' SEK';
        $result .= PHP_EOL.PHP_EOL;

        echo $result;
    }

    public function addTabs($label, $desiredColumnWidth = 45)
    {
        $currentLength = mb_strlen($label);
        $spacesNeeded = $desiredColumnWidth - $currentLength;

        $tabsCount = ceil($spacesNeeded / 8);

        $tabsCount = max($tabsCount, 1);

        return $label . str_repeat("\t", $tabsCount);
    }

    public function createSeparator(string $character = '-', string $name = '', int $totalWidth = 60): string
    {
        $text = $name;
        $lineLength = $totalWidth - mb_strlen($text);

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
            return $this->blueText(number_format($value, 2, '.', ' '));
        }
        return $value > 0 ? $this->greenText(number_format($value, 2, '.', ' ')) : $this->redText(number_format($value, 2, '.', ' '));
    }

    public function formatNumber(float $value): string
    {
        return number_format($value, 2, '.', ' ');
    }

    public function greyText($value): string
    {
        return "\033[38;5;245m" . $value . "\033[0m";
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
