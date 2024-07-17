<?php

declare(strict_types=1);

namespace Avk\View;

use Avk\DataStructure\FinancialOverview;
use Avk\DataStructure\FinancialAsset;
use Avk\Enum\TransactionType;

class Presenter
{
    /**
     * @param FinancialAsset[] $assets
     */
    public function displayDetailedAssets(array $assets): void
    {
        foreach ($assets as $asset) {
            echo PHP_EOL;

            echo TextColorizer::colorText($this->createSeparator('-', $asset->name .' ('.$asset->isin.')'), 'pink') . PHP_EOL;

            echo $this->addTabs('Köpbelopp:') . TextColorizer::colorText($this->formatNumber($asset->getBuyAmount()), 'blue') . ' SEK' . PHP_EOL;
            echo $this->addTabs('Säljbelopp:') . TextColorizer::colorText($this->formatNumber($asset->getSellAmount()), 'blue') . ' SEK' . PHP_EOL;
            echo $this->addTabs('Anskaffningsvärde:') . TextColorizer::colorText($this->formatNumber($asset->getCostBasis()), 'blue') . ' SEK' . PHP_EOL;

            echo PHP_EOL;

            echo $this->addTabs('Tot. courtage:', 50) . TextColorizer::colorText($this->formatNumber($asset->getCommissionBuyAmount() + $asset->getCommissionSellAmount()), 'red') . ' SEK' . PHP_EOL;
            // echo $this->addTabs('Courtage köp:', 50) . TextColorizer::colorText($this->formatNumber($asset->getCommissionBuyAmount()), 'red') . ' SEK' . PHP_EOL;
            // echo $this->addTabs('Courtage sälj:', 50) . TextColorizer::colorText($this->formatNumber($asset->getCommissionSellAmount()), 'red') . ' SEK' . PHP_EOL;
            echo $this->addTabs('Tot. avgifter:', 50) . TextColorizer::colorText($this->formatNumber($asset->getFeeAmount()), 'red') . ' SEK' . PHP_EOL;
            echo $this->addTabs('Utländsk källskatt:') . $this->colorPicker($asset->getForeignWithholdingTaxAmount()) . ' SEK' . PHP_EOL;

            echo PHP_EOL;

            $absolutePerformance = $asset->getDividendAmount() + $asset->getRealizedGainLoss() + $asset->getUnrealizedGainLoss() + $asset->getFeeAmount() - $asset->getCommissionBuyAmount() - $asset->getCommissionSellAmount();

            echo $this->addTabs('Orealiserad vinst/förlust:') . $this->colorPicker($asset->getUnrealizedGainLoss()) . ' SEK' . PHP_EOL;
            echo $this->addTabs('Realiserad vinst/förlust:') . $this->colorPicker($asset->getRealizedGainLoss()) . ' SEK' . PHP_EOL;
            echo $this->addTabs('Utdelningar:') . $this->colorPicker($asset->getDividendAmount()) . ' SEK' . PHP_EOL;
            echo $this->addTabs('Absolut avkastning:') . $this->colorPicker($absolutePerformance) . ' SEK' . PHP_EOL;

            echo PHP_EOL;

            echo $this->addTabs('Antal transaktioner: ', 50) . count($asset->getTransactions()) . PHP_EOL;
            echo $this->addTabs('Första transaktionen: ', 50) . $asset->getFirstTransactionDate() . PHP_EOL;
            echo $this->addTabs('Senaste transaktionen: ', 50) . $asset->getLastTransactionDate() . PHP_EOL;

            echo PHP_EOL;

            foreach ($asset->bankAccounts as $bank => $accounts) {
                $accountsStr = implode(', ', $accounts);
                echo $this->addTabs('Bank:', 50) . $bank . ' (' . $accountsStr . ')' . PHP_EOL;
            }

            echo PHP_EOL;

            if ((int) $asset->getCurrentNumberOfShares() > 0) {
                echo $this->addTabs('Nuvarande antal aktier:', 50) . number_format($asset->getCurrentNumberOfShares(), 2, '.', ' ') . ' st' . PHP_EOL;

                if ($asset->getCurrentValueOfShares() && $asset->getCurrentPricePerShare()) {
                    echo $this->addTabs('Nuvarande pris/aktie') . number_format($asset->getCurrentPricePerShare(), 2, '.', ' ') . ' SEK' . PHP_EOL;
                    echo $this->addTabs('Nuvarande värde:') . number_format($asset->getCurrentValueOfShares(), 2, '.', ' ') . ' SEK ' . PHP_EOL;
                } else {
                    echo TextColorizer::colorText('** Saknar kurspris **', 'yellow') . PHP_EOL;
                }

                echo PHP_EOL;
            }

            echo PHP_EOL;
        }
    }

    public function displayInvestmentReport(FinancialOverview $overview): void
    {
        $performance = $overview->getPerformance();

        $numberOfBuys = 0;
        $numberOfSells = 0;
        $numberOfDeposits = 0;
        $numberOfWithdrawals = 0;
        $numberOfDividends = 0;
        foreach ($overview->cashFlows as $cashFlow) {
            if ($cashFlow->type === TransactionType::BUY) {
                $numberOfBuys++;
            }
            if ($cashFlow->type === TransactionType::SELL) {
                $numberOfSells++;
            }
            if ($cashFlow->type === TransactionType::DEPOSIT) {
                $numberOfDeposits++;
            }
            if ($cashFlow->type === TransactionType::WITHDRAWAL) {
                $numberOfWithdrawals++;
            }
            if ($cashFlow->type === TransactionType::DIVIDEND) {
                $numberOfDividends++;
            }
        }

        echo PHP_EOL . TextColorizer::backgroundColor(str_pad("=== Investment Report ===", 70, "=", STR_PAD_BOTH), 'white') . PHP_EOL . PHP_EOL;
        echo "Från {$overview->getFirstTransactionDate()} till {$overview->getLastTransactionDate()}" . PHP_EOL . PHP_EOL;

        // 1. Investments
        echo TextColorizer::backgroundColor("1. Investeringar:") . PHP_EOL;
        echo str_pad("Köp och sälj:", 30) . PHP_EOL;
        echo str_pad(" ", 30) . "Totala köp: {$this->formatNumber($overview->totalBuyAmount)} SEK" . PHP_EOL;
        echo str_pad(" ", 30) . "Antal köp: {$numberOfBuys} st" . PHP_EOL;
        echo str_pad(" ", 30) . "Totala sälj: {$this->formatNumber($overview->totalSellAmount)} SEK" . PHP_EOL;
        echo str_pad(" ", 30) . "Antal sälj: {$numberOfSells} st" . PHP_EOL;

        echo str_pad("Utdelningar:", 30) . PHP_EOL;
        echo str_pad(" ", 30) . "Totala utdelningar: {$this->colorPicker($overview->totalDividend)} SEK" . PHP_EOL;
        echo str_pad(" ", 30) . "Antal utdelningar: {$numberOfDividends} st" . PHP_EOL;
        echo str_pad(" ", 30) . "Betald utländsk källskatt: {$this->colorPicker($overview->totalForeignWithholdingTax)} SEK" . PHP_EOL;
        echo str_pad(" ", 30) . "Återbetald utländsk källskatt: {$this->colorPicker($overview->totalReturnedForeignWithholdingTax)} SEK" . PHP_EOL;

        echo str_pad("Kapitalvinster", 30) . PHP_EOL;
        echo str_pad(" ", 30) . "Totalt realiserade kapitalvinster/förlust: {$this->colorPicker($performance->realizedGainLoss)} SEK" . PHP_EOL;
        echo str_pad(" ", 30) . "Totalt orealiserade kapitalvinster/förlust: {$this->colorPicker($performance->unrealizedGainLoss)} SEK" . PHP_EOL;

        // 2. Banking Transactions
        echo PHP_EOL;
        echo TextColorizer::backgroundColor("2. Banktransaktioner:") . PHP_EOL;
        echo str_pad("Insättning:", 30) . PHP_EOL;
        echo str_pad(" ", 30) . "Totala insättningar: {$this->formatNumber($overview->depositAmountTotal)} SEK" . PHP_EOL;
        echo str_pad(" ", 30) . "Antal insättningar: {$numberOfDeposits} st" . PHP_EOL;
        echo str_pad("Uttag:", 30) . PHP_EOL;
        echo str_pad(" ", 30) . "Totala uttag: {$this->formatNumber($overview->withdrawalAmountTotal)} SEK" . PHP_EOL;
        echo str_pad(" ", 30) . "Antal uttag: {$numberOfWithdrawals} st" . PHP_EOL;
        echo str_pad("Ränta:", 30) . PHP_EOL;
        echo str_pad(" ", 30) . "Totala räntor: {$this->colorPicker($overview->totalInterest)} SEK" . PHP_EOL;

        // 3. Fees and Taxes
        echo PHP_EOL;
        echo TextColorizer::backgroundColor("3. Avgifter och skatter:") . PHP_EOL;
        echo str_pad("Courtage:", 30) . PHP_EOL;
        echo str_pad(" ", 30) . "Totalt köpcourtage: " . TextColorizer::colorText($this->formatNumber($overview->totalBuyCommission), 'red') . " SEK" . PHP_EOL;
        echo str_pad(" ", 30) . "Totalt säljcourtage: " . TextColorizer::colorText($this->formatNumber($overview->totalSellCommission), 'red') . " SEK" . PHP_EOL;

        echo str_pad("Avgifter:", 30) . PHP_EOL;
        echo str_pad(" ", 30) . "Totala avgifter: {$this->colorPicker($overview->totalFee)} SEK" . PHP_EOL;

        echo str_pad("Skatter:", 30) . PHP_EOL;
        echo str_pad(" ", 30) . "Totala skatter: {$this->colorPicker($overview->totalTax)} SEK" . PHP_EOL;

        // 4. Current Portfolio Valuation
        echo PHP_EOL;
        echo TextColorizer::backgroundColor("4. Portföljvärde:") . PHP_EOL;
        echo str_pad("Nuvarande innehav:", 30) . PHP_EOL;
        echo str_pad(" ", 30) . "Totalt nuvarande innehav: {$this->formatNumber($overview->totalCurrentHoldings)} SEK" . PHP_EOL;
        echo str_pad("Likvider:", 30) . PHP_EOL;
        echo str_pad(" ", 30) . "Totalt likvider: " . $this->formatNumber($overview->calculateBalance($overview->cashFlows) - $overview->totalCurrentHoldings) . " SEK" . PHP_EOL;
        echo str_pad("Totalt portföljvärde:", 30) . PHP_EOL;
        echo str_pad(" ", 30) . "Totalt portföljvärde: {$this->formatNumber($overview->calculateBalance($overview->cashFlows))} SEK" . PHP_EOL;
        echo str_pad("Total avkastning:", 30) . PHP_EOL;

        $absolutePerformance = 0;
        $absolutePerformance += $performance->realizedGainLoss;
        $absolutePerformance += $performance->unrealizedGainLoss;
        $absolutePerformance += $overview->totalDividend;
        $absolutePerformance += $overview->totalInterest;
        $absolutePerformance += $overview->totalReturnedForeignWithholdingTax;
        $absolutePerformance += $overview->totalForeignWithholdingTax;
        $absolutePerformance += $overview->totalFee;
        $absolutePerformance += $overview->totalTax;
        $absolutePerformance -= $overview->totalBuyCommission + $overview->totalSellCommission;
        echo str_pad(" ", 30) . "Total avkastning (inkl. avgifter, källskatt, skatt, räntor): {$this->colorPicker($absolutePerformance)} SEK" . PHP_EOL;

        if ($performance->xirr !== null) {
            echo str_pad("Avkastningsberäkningar:", 30) . PHP_EOL;
            echo str_pad(" ", 30) . "XIRR: {$this->colorPicker($performance->xirr)} %" . PHP_EOL;
        }

        echo PHP_EOL . str_repeat("=", 70) . PHP_EOL;
    }

    /**
     * @param FinancialAsset[] $assets
     */
    public function generateAssetTable(FinancialOverview $overview, array $assets): void
    {
        $nameMaxLength = 20;

        $headers = [
            'Värdepapper',
            'ISIN',
            'Anskaffningsvärde',
            'Real. vinst',
            'Oreal. vinst',
            'Tot. utdel.',
            'Tot. courtage',
            'Nuvarande värde',
            // 'Bef. aktier'
        ];
        $colWidths = array_fill(0, count($headers), 0);

        foreach ($headers as $colIndex => $header) {
            $colWidths[$colIndex] = mb_strlen($header);
        }

        foreach ($assets as $asset) {
            if (!$asset->performance) {
                // continue;
            }

            $name = $asset->name;
            if (mb_strlen($name) > $nameMaxLength) {
                $name = $this->truncateName($name, $nameMaxLength);
            }

            $colWidths[0] = max($colWidths[0], mb_strlen($name));
            $colWidths[1] = max($colWidths[1], mb_strlen((string) $asset->isin));
            $colWidths[2] = max($colWidths[2], mb_strlen($this->formatNumber($asset->getCostBasis())));
            $colWidths[3] = max($colWidths[3], mb_strlen($this->formatNumber($asset->getRealizedGainLoss())));
            $colWidths[4] = max($colWidths[4], mb_strlen($this->formatNumber($asset->getUnrealizedGainLoss())));
            $colWidths[5] = max($colWidths[5], mb_strlen($this->formatNumber($asset->getDividendAmount())));
            $colWidths[6] = max($colWidths[6], mb_strlen($this->formatNumber($asset->getCommissionBuyAmount() + $asset->getCommissionSellAmount())));
            $colWidths[7] = max($colWidths[7], mb_strlen($this->formatNumber((float) $asset->getCurrentValueOfShares())));
        }

        // TODO: calculate this elsewhere
        $totalRealizedCapitalGainLoss = 0;
        $totalUnrealizedCapitalGainLoss = 0;
        $totalCostBasis = 0;
        foreach ($assets as $asset) {
            $totalRealizedCapitalGainLoss += $asset->getRealizedGainLoss();
            $totalUnrealizedCapitalGainLoss += $asset->getUnrealizedGainLoss();
            $totalCostBasis += $asset->getCostBasis();
        }

        $colWidths[2] = max($colWidths[2], mb_strlen($this->formatNumber($totalCostBasis)));
        $colWidths[3] = max($colWidths[3], mb_strlen($this->formatNumber($totalRealizedCapitalGainLoss)));
        $colWidths[4] = max($colWidths[4], mb_strlen($this->formatNumber($totalUnrealizedCapitalGainLoss)));
        $colWidths[5] = max($colWidths[5], mb_strlen($this->formatNumber($overview->totalDividend)));
        $colWidths[6] = max($colWidths[6], mb_strlen($this->formatNumber($overview->totalBuyCommission + $overview->totalSellCommission)));
        $colWidths[7] = max($colWidths[7], mb_strlen($this->formatNumber($overview->totalCurrentHoldings)));

        $this->printHorizontalLine($colWidths);
        $this->printRow($headers, $colWidths);
        $this->printHorizontalLine($colWidths);

        foreach ($assets as $asset) {
            if (!$asset->performance) {
                // continue;
            }
            $name = $asset->name;
            if (mb_strlen($name) > $nameMaxLength) {
                $name = $this->truncateName($name, $nameMaxLength);
            }
            $this->printRow([
                $name,
                $asset->isin,
                $this->formatNumber($asset->getCostBasis()),
                $this->formatNumber($asset->getRealizedGainLoss()),
                $this->formatNumber($asset->getUnrealizedGainLoss()),
                $this->formatNumber($asset->getDividendAmount()),
                $this->formatNumber($asset->getCommissionBuyAmount() + $asset->getCommissionSellAmount()),
                $this->formatNumber((float) $asset->getCurrentValueOfShares())
            ], $colWidths);
            $this->printHorizontalLine($colWidths);
        }



        $this->printRow([
            'Summering:',
            '-',
            $this->formatNumber($totalCostBasis),
            $this->formatNumber($totalRealizedCapitalGainLoss),
            $this->formatNumber($totalUnrealizedCapitalGainLoss),
            $this->formatNumber($overview->totalDividend),
            $this->formatNumber(($overview->totalBuyCommission + $overview->totalSellCommission)),
            $this->formatNumber($overview->totalCurrentHoldings)
        ], $colWidths);
        $this->printHorizontalLine($colWidths);
    }

    /**
     * @param FinancialAsset[] $assets
     */
    public function displayAssetNotices(array $assets): void
    {
        foreach ($assets as $asset) {
            foreach ($asset->notices as $notice) {
                echo TextColorizer::colorText($notice, 'blue') . PHP_EOL;
            }
        }
    }

    /**
     * @param mixed[] $colWidths
     */
    public function printHorizontalLine(array $colWidths): void
    {
        foreach ($colWidths as $width) {
            echo '+' . str_repeat('-', intval($width) + 2);
        }
        echo '+' . PHP_EOL;
    }


    /**
     * @param mixed[] $row
     * @param mixed[] $colWidths
     */
    public function printRow(array $row, array $colWidths): void
    {
        foreach ($row as $colIndex => $colValue) {
            $visibleLength = mb_strlen($colValue);
            $padding = $colWidths[$colIndex] - $visibleLength;
            printf("| %s%s ", $colValue, str_repeat(' ', intval($padding)));
        }
        echo '|' . PHP_EOL;
    }

    public function printRelativeProgressBarPercentage(string $label, float $value, float $maxValue): void
    {
        $maxWidth = 50;
        $relativeWidth = ($value / $maxValue) * $maxWidth;
        $currentWidth = (int) round($relativeWidth);

        $bar = str_pad($this->truncateName($label, 20), 20) . ' |';

        $bar .= TextColorizer::colorText(str_repeat('█', $currentWidth), 'blue');
        $bar .= str_repeat(' ', $maxWidth - $currentWidth);
        $bar .= '| ' . TextColorizer::colorText(sprintf("%.2f%%", $value), 'cyan');

        echo $bar . PHP_EOL;
    }

    public function printRelativeProgressBarAmount(string $label, float $value, float $maxValue): void
    {
        $maxWidth = 50;
        $relativeWidth = ($value / $maxValue) * $maxWidth;
        $currentWidth = (int) round($relativeWidth);

        $bar = str_pad($this->truncateName($label, 20), 20) . ' |';

        $bar .= TextColorizer::colorText(str_repeat('█', $currentWidth), 'blue');
        $bar .= str_repeat(' ', $maxWidth - $currentWidth);
        $bar .= '| ' . TextColorizer::colorText($value, 'cyan');

        echo $bar . PHP_EOL;
    }

    public function addTabs(string $label, int $desiredColumnWidth = 45): string
    {
        $currentLength = mb_strlen($label);
        $spacesNeeded = $desiredColumnWidth - $currentLength;

        $tabsCount = ceil($spacesNeeded / 8);

        $tabsCount = intval(max($tabsCount, 1));

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
            return TextColorizer::colorText($this->formatNumber($value), 'blue');
        }

        $color = $value > 0 ? 'green' : 'red';
        return TextColorizer::colorText($this->formatNumber($value), $color);
    }

    public function formatNumber(float $value): string
    {
        return number_format($value, 2, '.', ' ');
    }
}
