<?php

namespace src\Libs;

use src\DataStructure\Overview;
use src\DataStructure\TransactionSummary;

class Presenter
{
    private const TAB_SIZE = 4;

    /**
     * @param TransactionSummary[] $summaries
     */
    public function displayDetailedSummaries(array $summaries): void
    {
        foreach ($summaries as $summary) {
            echo PHP_EOL;

            echo $this->pinkText($this->createSeparator('-', $summary->name .' ('.$summary->isin.')')) . PHP_EOL;

            echo $this->addTabs('Köpbelopp:') . $this->cyanText(number_format($summary->buy, 2, '.', ' ')) . ' SEK' . PHP_EOL;
            echo $this->addTabs('Säljbelopp:') . $this->blueText(number_format($summary->sell, 2, '.', ' ')) . ' SEK' . PHP_EOL;
            echo $this->addTabs('Utdelningar:') . $this->colorPicker($summary->dividend) . ' SEK' . PHP_EOL;

            echo PHP_EOL;

            echo $this->addTabs('Tot. courtage:', 50) . $this->redText($summary->commissionBuy + $summary->commissionSell) . ' SEK' . PHP_EOL;
            echo $this->addTabs('Courtage köp:', 50) . $this->redText($summary->commissionBuy) . ' SEK' . PHP_EOL;
            echo $this->addTabs('Courtage sälj:', 50) . $this->redText($summary->commissionSell) . ' SEK' . PHP_EOL;

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
                // echo $this->addTabs('Tot. avkastning:') . $this->colorPicker($summary->assetReturn->totalReturnExclFees) . ' SEK' . PHP_EOL;
                // echo $this->addTabs('Tot. avkastning:') . $this->colorPicker($summary->assetReturn->totalReturnExclFeesPercent) . ' %' . PHP_EOL;

                echo $this->addTabs('Tot. avkastning (m. avgifter):', 50) . $this->colorPicker($summary->assetReturn->totalReturnInclFees) . ' SEK' . PHP_EOL;
            }

            echo PHP_EOL;
        }
    }

    public function displayOverview(Overview $overview): void
    {
        $currentBalance = $overview->calculateBalance($overview->cashFlows) - $overview->totalCurrentHoldings;
        echo 'Saldo (likvider): ' . $this->colorPicker($currentBalance) . ' SEK' . PHP_EOL;
        echo 'Totalt värde: ' . $this->colorPicker($overview->calculateBalance($overview->cashFlows)) . ' SEK' . PHP_EOL;

        print_r($overview->currentHoldingsWeighting);

        // TODO: Move this somewhere suitable (Presenter?)
        echo 'Tot. courtage: ' . $this->redText($overview->totalBuyCommission + $overview->totalSellCommission) . ' SEK' . PHP_EOL;
        echo 'Tot. köp-courtage: ' . $this->redText($overview->totalBuyCommission) . ' SEK' . PHP_EOL;
        echo 'Tot. sälj-courtage: ' . $this->redText($overview->totalSellCommission) . ' SEK' . PHP_EOL;
        echo 'Tot. avgifter: ' . $this->redText($overview->totalFee) . ' SEK' . PHP_EOL;
        echo 'Tot. skatt: ' . $this->redText($overview->totalTax) . ' SEK' . PHP_EOL;
        echo 'Tot. utländsk källskatt: ' . $this->redText($overview->totalForeignWithholdingTax) . ' SEK' . PHP_EOL;
        echo PHP_EOL;
        echo 'Tot. återbetald utländsk källskatt: ' . $this->colorPicker($overview->totalReturnedForeignWithholdingTax) . ' SEK' . PHP_EOL;
        echo 'Tot. utdelningar: ' . $this->colorPicker($overview->totalDividend) . ' SEK' . PHP_EOL;
        echo 'Tot. ränta: ' . $this->colorPicker($overview->totalInterest) . ' SEK' . PHP_EOL;
        echo PHP_EOL;
        echo 'Tot. köpbelopp: ' . $this->colorPicker($overview->totalBuyAmount) . ' SEK' . PHP_EOL;
        echo 'Tot. säljbelopp: ' . $this->colorPicker($overview->totalSellAmount) . ' SEK' . PHP_EOL;
        echo PHP_EOL;
        echo 'Tot. insättningar: ' . $this->colorPicker($overview->depositAmountTotal) . ' SEK' . PHP_EOL;
        echo 'Tot. uttag: ' . $this->colorPicker($overview->withdrawalAmountTotal) . ' SEK' . PHP_EOL;
        echo PHP_EOL;
        echo 'Tot. nuvarande innehav: ' . $this->colorPicker($overview->totalCurrentHoldings) . ' SEK' . PHP_EOL;
        echo PHP_EOL;
        echo 'Tot. avkastning: ' . $this->colorPicker($overview->returns->totalReturnInclFees) . ' SEK' . PHP_EOL;
        echo PHP_EOL;
    }

    public function truncateName(string $name, int $maxLength): string
    {
        if (strlen($name) > $maxLength) {
            return substr($name, 0, $maxLength - 3) . '...';
        }

        return $name;
    }

    /**
     * @param TransactionSummary[] $summaries
     */
    public function generateSummaryTable(array $summaries): void
    {
        $headers = ['Värdepapper', 'ISIN', 'Avk. SEK', 'Tot. utdelning', 'Tot. courtage', 'Nuv. värde'];

        $nameMaxLength = 30;

        $colWidths = array_fill(0, count($headers), 0);

        foreach ($headers as $colIndex => $header) {
            $colWidths[$colIndex] = mb_strlen($header);
        }

        foreach ($summaries as $summary) {
            if (!$summary->assetReturn) {
                continue;
            }

            $name = $summary->name;
            if (mb_strlen($name) > $nameMaxLength) {
                $name = $this->truncateName($name, $nameMaxLength);
            }

            $colWidths[0] = max($colWidths[0], mb_strlen($name));
            $colWidths[1] = max($colWidths[1], mb_strlen($summary->isin));
            $colWidths[2] = max($colWidths[2], mb_strlen($this->formatNumber($summary->assetReturn->totalReturnInclFees) . ' SEK'));
            $colWidths[3] = max($colWidths[3], mb_strlen($this->formatNumber($summary->dividend) . ' SEK'));
            $colWidths[4] = max($colWidths[4], mb_strlen($this->formatNumber($summary->commissionBuy + $summary->commissionSell) . ' SEK'));
            $colWidths[5] = max($colWidths[5], mb_strlen($this->formatNumber($summary->currentValueOfShares) . ' SEK'));
        }

        $this->printHorizontalLine($colWidths);
        $this->printRow($headers, $colWidths);
        $this->printHorizontalLine($colWidths);

        foreach ($summaries as $summary) {
            if (!$summary->assetReturn) {
                continue;
            }
            $name = $summary->name;
            if (mb_strlen($name) > $nameMaxLength) {
                $name = $this->truncateName($name, $nameMaxLength);
            }
            $this->printRow([
                $name,
                $summary->isin,
                $this->formatNumber($summary->assetReturn->totalReturnInclFees) . ' SEK',
                $this->formatNumber($summary->dividend) . ' SEK',
                $this->formatNumber($summary->commissionBuy + $summary->commissionSell) . ' SEK',
                $this->formatNumber($summary->currentValueOfShares) . ' SEK'
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
