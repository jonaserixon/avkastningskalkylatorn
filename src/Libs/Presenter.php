<?php

namespace src\Libs;

use src\DataStructure\FinancialOverview;
use src\DataStructure\FinancialAsset;

class Presenter
{
    private const TAB_SIZE = 4;

    /**
     * @param FinancialAsset[] $assets
     */
    public function displayDetailedAssets(array $assets): void
    {
        foreach ($assets as $asset) {
            echo PHP_EOL;

            echo $this->pinkText($this->createSeparator('-', $asset->name .' ('.$asset->isin.')')) . PHP_EOL;

            echo $this->addTabs('Köpbelopp:') . $this->cyanText(number_format($asset->getBuyAmount(), 2, '.', ' ')) . ' SEK' . PHP_EOL;
            echo $this->addTabs('Säljbelopp:') . $this->blueText(number_format($asset->getSellAmount(), 2, '.', ' ')) . ' SEK' . PHP_EOL;
            echo $this->addTabs('Utdelningar:') . $this->colorPicker($asset->getDividendAmount()) . ' SEK' . PHP_EOL;

            echo PHP_EOL;

            echo $this->addTabs('Tot. courtage:', 50) . $this->redText($asset->getCommissionBuyAmount() + $asset->getCommissionSellAmount()) . ' SEK' . PHP_EOL;
            echo $this->addTabs('Courtage köp:', 50) . $this->redText($asset->getCommissionBuyAmount()) . ' SEK' . PHP_EOL;
            echo $this->addTabs('Courtage sälj:', 50) . $this->redText($asset->getCommissionSellAmount()) . ' SEK' . PHP_EOL;
            echo $this->addTabs('Tot. avgifter:', 50) . $this->redText($asset->getFeeAmount()) . ' SEK' . PHP_EOL;
            echo $this->addTabs('Utländsk källskatt:') . $this->colorPicker($asset->getForeignWithholdingTaxAmount()) . ' SEK' . PHP_EOL;

            echo PHP_EOL;

            echo $this->addTabs('Första transaktionen: ', 50) . $asset->getFirstTransactionDate() . PHP_EOL;
            echo $this->addTabs('Senaste transaktionen: ', 50) . $asset->getLastTransactionDate() . PHP_EOL;

            echo PHP_EOL;

            foreach ($asset->bankAccounts as $bank => $accounts) {
                echo $this->addTabs('Bank:', 50) . $bank . PHP_EOL;
                foreach ($accounts as $account) {
                    echo $this->addTabs('Konto:', 50) . $account . PHP_EOL;
                }
            }

            echo PHP_EOL;

            if ((int) $asset->getCurrentNumberOfShares() > 0) {
                echo $this->addTabs('Nuvarande antal aktier:', 50) . number_format($asset->getCurrentNumberOfShares(), 2, '.', ' ') . ' st' . PHP_EOL;

                if ($asset->getCurrentValueOfShares()) {
                    echo $this->addTabs('Nuvarande pris/aktie') . number_format($asset->getCurrentPricePerShare(), 2, '.', ' ') . ' SEK' . PHP_EOL;
                    echo $this->addTabs('Nuvarande markn.värde av aktier:') . number_format($asset->getCurrentValueOfShares(), 2, '.', ' ') . ' SEK ' . PHP_EOL;
                } else {
                    echo $this->yellowText('** Saknar kurspris **') . PHP_EOL;
                }

                echo PHP_EOL;
            }

            if ($asset->assetReturn) {
                // echo $this->addTabs('Tot. avkastning:') . $this->colorPicker($asset->assetReturn->totalReturnExclFees) . ' SEK' . PHP_EOL;
                // echo $this->addTabs('Tot. avkastning:') . $this->colorPicker($asset->assetReturn->totalReturnExclFeesPercent) . ' %' . PHP_EOL;

                echo $this->addTabs('Tot. avkastning (m. avgifter):', 50) . $this->colorPicker($asset->assetReturn->totalReturnInclFees) . ' SEK' . PHP_EOL;
            }

            echo PHP_EOL;
        }
    }

    public function displayInvestmentReport(FinancialOverview $overview, array $assets): void
    {
        $numberOfBuys = 0;
        $numberOfSells = 0;
        $numberOfDeposits = 0;
        $numberOfWithdrawals = 0;
        $numberOfDividends = 0;
        foreach ($overview->cashFlows as $cashFlow) {
            if ($cashFlow->type === 'buy') {
                $numberOfBuys++;
            }
            if ($cashFlow->type === 'sell') {
                $numberOfSells++;
            }
            if ($cashFlow->type === 'deposit') {
                $numberOfDeposits++;
            }
            if ($cashFlow->type === 'withdrawal') {
                $numberOfWithdrawals++;
            }
            if ($cashFlow->type === 'dividend') {
                $numberOfDividends++;
            }
        }

        echo "\n" . str_pad("=== Investment Report ===", 70, "=", STR_PAD_BOTH) . "\n\n";
        echo "Från {$overview->firstTransactionDate} till {$overview->lastTransactionDate}\n\n";

        // 1. Investments
        echo "1. Investeringar:\n";
        echo str_pad("Köp och sälj:", 30) . PHP_EOL;
        echo str_pad(" ", 30) . "Totala köp: {$this->formatNumber($overview->totalBuyAmount)} SEK" . PHP_EOL;
        echo str_pad(" ", 30) . "Antal köp: {$numberOfBuys} st" . PHP_EOL;
        echo str_pad(" ", 30) . "Totala sälj: {$this->formatNumber($overview->totalSellAmount)} SEK" . PHP_EOL;
        echo str_pad(" ", 30) . "Antal sälj: {$numberOfSells} st" . PHP_EOL;
        
        echo str_pad("Utdelningar:", 30) . PHP_EOL;
        echo str_pad(" ", 30) . "Totala utdelningar: {$this->formatNumber($overview->totalDividend)} SEK" . PHP_EOL;
        echo str_pad(" ", 30) . "Antal utdelningar: {$numberOfDividends} st" . PHP_EOL;
        echo str_pad(" ", 30) . "Betald utländsk källskatt: {$this->formatNumber($overview->totalForeignWithholdingTax)} SEK" . PHP_EOL;
        echo str_pad(" ", 30) . "Återbetald utländsk källskatt: {$this->formatNumber($overview->totalReturnedForeignWithholdingTax)} SEK" . PHP_EOL;

        $totalRealizedCapitalGainLoss = 0;
        $totalUnrealizedCapitalGainLoss = 0;
        foreach ($assets as $asset) {
            $totalRealizedCapitalGainLoss += $asset->realizedGainLoss;
            $totalUnrealizedCapitalGainLoss += $asset->unrealizedGainLoss;
        }

        // print_r($assets);
        // exit;

        echo str_pad("Kapitalvinster", 30) . PHP_EOL;
        echo str_pad(" ", 30) . "Totala realiserade kapitalvinster: {$this->formatNumber($totalRealizedCapitalGainLoss)} SEK" . PHP_EOL;
        echo str_pad(" ", 30) . "Totala orealiserade kapitalvinster: {$this->formatNumber($totalUnrealizedCapitalGainLoss)} SEK" . PHP_EOL;
        
        
        // 2. Banking Transactions
        echo PHP_EOL;
        echo "\n2. Banktransaktioner:\n";
        echo str_pad("Insättning:", 30) . PHP_EOL;
        echo str_pad(" ", 30) . "Totala insättningar: {$this->formatNumber($overview->depositAmountTotal)} SEK" . PHP_EOL;
        echo str_pad(" ", 30) . "Antal insättningar: {$numberOfDeposits} st" . PHP_EOL;
        echo str_pad("Uttag:", 30) . PHP_EOL;
        echo str_pad(" ", 30) . "Totala uttag: {$this->formatNumber($overview->withdrawalAmountTotal)} SEK" . PHP_EOL;
        echo str_pad(" ", 30) . "Antal uttag: {$numberOfWithdrawals} st" . PHP_EOL;
        echo str_pad("Ränta:", 30) . PHP_EOL;
        echo str_pad(" ", 30) . "Totala räntor: {$this->formatNumber($overview->totalInterest)} SEK" . PHP_EOL;

        // 3. Fees and Taxes
        echo PHP_EOL;
        echo "\n3. Avgifter och skatter:\n";
        echo str_pad("Courtage:", 30) . PHP_EOL;
        echo str_pad(" ", 30) . "Totalt köpcourtage: " . $this->formatNumber($overview->totalBuyCommission) . " SEK" . PHP_EOL;
        echo str_pad(" ", 30) . "Totalt säljcourtage: " . $this->formatNumber($overview->totalSellCommission) . " SEK" . PHP_EOL;

        echo str_pad("Avgifter:", 30) . PHP_EOL;
        echo str_pad(" ", 30) . "Totala avgifter: {$this->formatNumber($overview->totalFee)} SEK" . PHP_EOL;

        echo str_pad("Skatter:", 30) . PHP_EOL;
        echo str_pad(" ", 30) . "Totala skatter: {$this->formatNumber($overview->totalTax)} SEK" . PHP_EOL;

        // 4. Current Portfolio Valuation
        echo PHP_EOL;
        echo "\n4. Portföljvärde:\n";
        echo str_pad("Nuvarande innehav:", 30) . PHP_EOL;
        echo str_pad(" ", 30) . "Totalt nuvarande innehav: {$this->formatNumber($overview->totalCurrentHoldings)} SEK" . PHP_EOL;
        echo str_pad("Likvider:", 30) . PHP_EOL;
        echo str_pad(" ", 30) . "Totalt likvider: " . $this->formatNumber($overview->calculateBalance($overview->cashFlows) - $overview->totalCurrentHoldings) . " SEK" . PHP_EOL;
        echo str_pad("Totalt portföljvärde:", 30) . PHP_EOL;
        echo str_pad(" ", 30) . "Totalt portföljvärde: {$this->formatNumber($overview->calculateBalance($overview->cashFlows))} SEK" . PHP_EOL;

        echo "\n" . str_repeat("=", 70) . "\n";
    }

    public function displayFinancialOverview(FinancialOverview $overview): void
    {
        $currentBalance = $overview->calculateBalance($overview->cashFlows) - $overview->totalCurrentHoldings;
        echo 'Saldo (likvider): ' . $this->colorPicker($currentBalance) . ' SEK' . PHP_EOL;
        echo 'Totalt värde: ' . $this->colorPicker($overview->calculateBalance($overview->cashFlows)) . ' SEK' . PHP_EOL;

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
        echo 'Tot. avkastning (inkl. avgifter, källskatt, skatt): ' . $this->colorPicker($overview->returns->totalReturnInclFees) . ' SEK' . PHP_EOL;
        echo PHP_EOL;
    }

    /**
     * @param FinancialAsset[] $assets
     */
    public function generateAssetTable(FinancialOverview $overview, array $assets): void
    {
        $nameMaxLength = 30;

        $headers = ['Värdepapper', 'ISIN', 'Avkastning (kr)', 'Tot. utdelning (kr)', 'Tot. courtage (kr)', 'Nuvarande värde (kr)'];
        $colWidths = array_fill(0, count($headers), 0);

        foreach ($headers as $colIndex => $header) {
            $colWidths[$colIndex] = mb_strlen($header);
        }

        foreach ($assets as $asset) {
            if (!$asset->assetReturn) {
                continue;
            }

            $name = $asset->name;
            if (mb_strlen($name) > $nameMaxLength) {
                $name = $this->truncateName($name, $nameMaxLength);
            }

            $colWidths[0] = max($colWidths[0], mb_strlen($name));
            $colWidths[1] = max($colWidths[1], mb_strlen($asset->isin));
            $colWidths[2] = max($colWidths[2], mb_strlen($this->formatNumber($asset->assetReturn->totalReturnInclFees)));
            $colWidths[3] = max($colWidths[3], mb_strlen($this->formatNumber($asset->getDividendAmount())));
            $colWidths[4] = max($colWidths[4], mb_strlen($this->formatNumber($asset->getCommissionBuyAmount() + $asset->getCommissionSellAmount())));
            $colWidths[5] = max($colWidths[5], mb_strlen($this->formatNumber($asset->getCurrentValueOfShares())));
        }

        $this->printHorizontalLine($colWidths);
        $this->printRow($headers, $colWidths);
        $this->printHorizontalLine($colWidths);

        foreach ($assets as $asset) {
            if (!$asset->assetReturn) {
                continue;
            }
            $name = $asset->name;
            if (mb_strlen($name) > $nameMaxLength) {
                $name = $this->truncateName($name, $nameMaxLength);
            }
            $this->printRow([
                $name,
                $asset->isin,
                $this->formatNumber($asset->assetReturn->totalReturnInclFees),
                $this->formatNumber($asset->getDividendAmount()),
                $this->formatNumber($asset->getCommissionBuyAmount() + $asset->getCommissionSellAmount()),
                $this->formatNumber($asset->getCurrentValueOfShares())
            ], $colWidths);
            $this->printHorizontalLine($colWidths);
        }

        $this->printRow([
            'Summering:',
            '-',
            $this->formatNumber($overview->returns->totalReturnInclFees),
            $this->formatNumber($overview->totalDividend),
            $this->formatNumber(($overview->totalBuyCommission + $overview->totalSellCommission)),
            $this->formatNumber($overview->totalCurrentHoldings)
        ], $colWidths);
        $this->printHorizontalLine($colWidths);

        /*
        $this->printRow([
            'Saldo:',
            '-',
            '-',
            '-',
            '-',
            '-'
        ], $colWidths);
        $this->printHorizontalLine($colWidths);
        */
    }

    public function printHorizontalLine(array $colWidths): void
    {
        foreach ($colWidths as $width) {
            echo '+' . str_repeat('-', $width + 2);
        }
        echo '+' . PHP_EOL;
    }

    public function printRow(array $row, array $colWidths): void
    {
        foreach ($row as $colIndex => $colValue) {
            $visibleLength = mb_strlen($colValue);
            $padding = $colWidths[$colIndex] - $visibleLength;
            printf("| %s%s ", $colValue, str_repeat(' ', $padding));
        }
        echo '|' . PHP_EOL;
    }

    public function printRelativeProgressBar(string $label, float $value, float $maxValue): void
    {
        $maxWidth = 50;
        $relativeWidth = ($value / $maxValue) * $maxWidth;
        $currentWidth = (int) round($relativeWidth);

        $bar = str_pad($this->truncateName($label, 20), 20) . ' |';

        $bar .= $this->blueText(str_repeat('█', $currentWidth));
        $bar .= str_repeat(' ', $maxWidth - $currentWidth);
        $bar .= '| ' . $this->cyanText(sprintf("%.2f%%", $value));

        echo $bar . PHP_EOL;
    }

    public function displayCompactFormattedAsset(FinancialAsset $asset): void
    {
        if (!$asset->assetReturn) {
            return;
        }

        $assetName = $asset->name .' ('.$asset->isin.')';
        $assetNameTextLength = mb_strlen($assetName);

        // Calculate the number of spaces needed to align text
        $spaces = str_repeat(' ', (70 - $assetNameTextLength + self::TAB_SIZE) < 0 ? 0 : (70 - $assetNameTextLength + self::TAB_SIZE));

        $result = $this->pinkText($assetName);
        $result .= $spaces;
        $result .= ' | ';
        $result .= $this->colorPicker($asset->assetReturn->totalReturnInclFees) . ' SEK';
        $result .= PHP_EOL.PHP_EOL;

        echo $result;
    }

    public function addTabs(string $label, int $desiredColumnWidth = 45): string
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

    public function truncateName(string $name, int $maxLength): string
    {
        if (mb_strlen($name) > $maxLength) {
            return mb_substr($name, 0, $maxLength - 4) . '...';
        }

        return $name;
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

    public function greyText(float|string $value): string
    {
        return "\033[38;5;245m" . $value . "\033[0m";
    }

    public function pinkText(float|string $value): string
    {
        return "\033[38;5;213m" . $value . "\033[0m";
    }

    public function greenText(float|string $value): string
    {
        return "\033[32m" . $value . "\033[0m";
    }

    public function redText(float|string $value): string
    {
        return "\033[31m" . $value . "\033[0m";
    }

    public function blueText(float|string $value): string
    {
        return "\033[1;34m" . $value . "\033[0m";
    }

    public function cyanText(float|string $text): string
    {
        return "\033[36m" . $text . "\033[0m";
    }

    public function yellowText(float|string $text): string
    {
        return "\033[33m" . $text . "\033[0m";
    }
}
