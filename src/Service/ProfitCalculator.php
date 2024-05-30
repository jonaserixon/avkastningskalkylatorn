<?php

namespace src\Service;

use src\DataStructure\AssetReturn;
use src\DataStructure\FinancialAsset;
use src\DataStructure\FinancialOverview;
use src\DataStructure\Transaction;
use src\Enum\Bank;
use src\Enum\TransactionType;
use src\Service\FileManager\CsvProcessor\StockPrice;
use src\View\Logger;
use stdClass;

class ProfitCalculator
{
    private bool $filterCurrentHoldings;
    private StockPrice $stockPrice;

    public function __construct(bool $currentHoldings)
    {
        $this->filterCurrentHoldings = $currentHoldings;
        $this->stockPrice = new StockPrice();
    }

    /**
     * Calculate realized gains, unrealized gains, current value of shares and total return for each asset.
     *
     * @param FinancialAsset[] $assets
     * @param FinancialOverview $overview
     * @return stdClass
     */
    public function calculate(array $assets, FinancialOverview $overview): stdClass
    {
        $currentHoldingsMissingPricePerShare = [];
        $filteredAssets = [];
        foreach ($assets as $asset) {
            if ($this->filterCurrentHoldings && (int) $asset->getCurrentNumberOfShares() <= 0) {
                continue;
            }

            // Skapa en kopia av transaktionerna här, vi vill inte påverka originaldatat.
            $mergedTransactions = array_merge(
                array_map(function ($item) { return clone $item; }, $asset->getTransactionsByType(TransactionType::BUY)),
                array_map(function ($item) { return clone $item; }, $asset->getTransactionsByType(TransactionType::SELL)),
                array_map(function ($item) { return clone $item; }, $asset->getTransactionsByType(TransactionType::SHARE_SPLIT))
            );

            if (!empty($mergedTransactions)) {
                // Viktigt att sortera transaktionerna efter datum inför beräkningar.
                usort($mergedTransactions, function ($a, $b) {
                    return strcasecmp($a->getDateString(), $b->getDateString());
                });

                $result = $this->calculateRealizedGains($mergedTransactions);
                $asset->realizedGainLoss = $result->realizedGain;
                $asset->costBasis = $result->remainingCostBase;
            }
            unset($mergedTransactions);

            // Temporary solution for Avanzas handling of share transfers from Avanza to another bank(?).
            if (!empty($asset->hasTransactionOfType(TransactionType::SHARE_TRANSFER)) && empty($asset->getCurrentNumberOfShares()) && $asset->getBuyAmount() < 0) {
                $shareTransferQuantity = 0;
                $shareTransferAmount = 0;
                foreach ($asset->getTransactionsByType(TransactionType::SHARE_TRANSFER) as $shareTransfer) {
                    $shareTransferAmount += $shareTransfer->getRawQuantity() * $shareTransfer->getRawPrice();
                    $shareTransferQuantity += $shareTransfer->getRawQuantity();
                }

                if ($shareTransferQuantity != 0) {
                    Logger::getInstance()->addNotice("Share transfer(s) for {$asset->name} needs to be double checked. Amount: " . $shareTransferAmount . " (" . round($asset->costBasis + $shareTransferAmount, 3) . ")");
                }
            }

            if (!empty($asset->isin)) {
                $currentPricePerShare = $this->stockPrice->getCurrentPriceByIsin($asset->isin);

                if ($currentPricePerShare && (int) $asset->getCurrentNumberOfShares() > 0) {
                    // $asset->name = $this->stockPrice->getNameByIsin($asset->isin);
                    $currentValueOfShares = $asset->getCurrentNumberOfShares() * $currentPricePerShare;

                    $overview->totalCurrentHoldings += $currentValueOfShares;
                    $overview->addCashFlow(
                        date('Y-m-d'),
                        $currentValueOfShares,
                        $asset->name,
                        TransactionType::CURRENT_HOLDING,
                        '-',
                        Bank::NOT_SPECIFIED
                    );
                    $overview->lastTransactionDate = date('Y-m-d');

                    $asset->setCurrentPricePerShare($currentPricePerShare);
                    $asset->setCurrentValueOfShares($currentValueOfShares);
                    $asset->assetReturn = $this->calculateTotalReturnForAsset($asset);

                    $asset->unrealizedGainLoss = $asset->getCurrentValueOfShares() - $asset->costBasis;

                    $filteredAssets[] = $asset;

                    continue;
                }

                $asset->assetReturn = $this->calculateTotalReturnForAsset($asset);

                $isMissingPricePerShare = (int) $asset->getCurrentNumberOfShares() > 0 && !$currentPricePerShare;

                if ($isMissingPricePerShare) {
                    $currentHoldingsMissingPricePerShare[] = $asset->name . ' (' . $asset->isin . ')';
                }
            }
        }

        // Important for calculations etc.
        usort($overview->cashFlows, function ($a, $b) {
            return strtotime($a->getDateString()) <=> strtotime($b->getDateString());
        });

        $result = new stdClass();
        $result->currentHoldingsMissingPricePerShare = $currentHoldingsMissingPricePerShare;

        if ($this->filterCurrentHoldings) {
            $result->assets = $filteredAssets;
        } else {
            $result->assets = $assets;
        }

        $overview->returns = $this->calculateTotalReturnForFinancialOverview($overview);

        $result->overview = $overview;
        $this->calculateCurrentHoldingsWeighting($result->overview, $result->assets);

        return $result;
    }

    /**
     * Calculate the current holdings weighting for each asset.
     * @param FinancialOverview $overview
     * @param FinancialAsset[] $assets
     */
    protected function calculateCurrentHoldingsWeighting(FinancialOverview $overview, array $assets): void
    {
        foreach ($assets as $asset) {
            if ($asset->getCurrentValueOfShares() > 0) {
                $weighting = $asset->getCurrentValueOfShares() / $overview->totalCurrentHoldings * 100;
                $overview->currentHoldingsWeighting[$asset->name] = round($weighting, 4);
            }
        }
    }

    protected function calculateTotalReturnForAsset(FinancialAsset $asset): ?AssetReturn
    {
        if ($asset->getCurrentValueOfShares() === null) {
            $asset->setCurrentValueOfShares(0);
        }

        $totalReturnInclFees = 0;
        $totalReturnInclFees += $asset->getBuyAmount();
        $totalReturnInclFees += $asset->getSellAmount();
        $totalReturnInclFees += $asset->getDividendAmount();
        $totalReturnInclFees += $asset->getFeeAmount();
        $totalReturnInclFees += $asset->getCurrentValueOfShares();

        $result = new AssetReturn();
        $result->totalReturnInclFees = $totalReturnInclFees;

        // $this->transactionMapper->overview->totalProfitInclFees += $totalReturnInclFees;

        return $result;
    }

    protected function calculateTotalReturnForFinancialOverview(FinancialOverview $overview): AssetReturn
    {
        $totalReturnInclFees = 0;
        $totalReturnInclFees += $overview->totalSellAmount;
        $totalReturnInclFees += $overview->totalDividend;
        $totalReturnInclFees += $overview->totalCurrentHoldings;
        $totalReturnInclFees += $overview->totalBuyAmount;
        $totalReturnInclFees += $overview->totalFee;
        $totalReturnInclFees += $overview->totalTax;
        $totalReturnInclFees += $overview->totalInterest;
        $totalReturnInclFees += $overview->totalForeignWithholdingTax;
        $totalReturnInclFees += $overview->totalReturnedForeignWithholdingTax;

        $result = new AssetReturn();
        $result->totalReturnInclFees = $totalReturnInclFees;

        return $result;
    }

    /*
    public function _calculateRealizedGains(array $transactions): stdClass
    {
        $totalCost = 0;
        $totalShares = 0;
        $realizedGain = 0;

        foreach ($transactions as $transaction) {
            if ($transaction->type === 'buy') {
                // Lägg till köpkostnad och öka antalet aktier
                $totalCost += abs($transaction->rawAmount);
                $totalShares += abs($transaction->rawQuantity);
            } elseif ($transaction->type === 'sell') {
                // Endast räkna kapitalvinst om det finns köpta aktier att sälja
                if ($totalShares > 0 && $totalShares >= abs($transaction->rawQuantity)) {
                    $costPerShare = $totalCost / $totalShares;
                    $sellCost = $costPerShare * abs($transaction->rawQuantity);

                    // Räkna ut kapitalvinsten för de sålda aktierna
                    $realizedGain += (abs($transaction->rawAmount) - $sellCost);

                    // Minska den totala kostnaden och antalet aktier
                    $totalCost -= $sellCost;
                    $totalShares -= abs($transaction->rawQuantity);
                }
            } elseif ($transaction->type === 'share_split') {
                if ($totalShares != 0) {
                    $totalShares += $transaction->rawQuantity;
                }
            }
        }

        $result = new stdClass();
        $result->remainingCostBase = $totalCost;
        $result->realizedGain = $realizedGain;

        return $result;
    }
    */

    /**
     * Calculate realized gains and cost basis for a list of transactions.
     *
     * @param Transaction[] $transactions
     * @return stdClass
     */
    public function calculateRealizedGains(array $transactions): stdClass
    {
        $totalCost = '0.0';
        $totalQuantity = '0.0';
        $realizedGain = '0.0';
        $actualQuantity = 0;

        $scale = 15;

        foreach ($transactions as $transaction) {
            if ($transaction->getRawAmount() === null || $transaction->getRawQuantity() === null) {
                continue;
            }

            $actualQuantity += $transaction->getRawQuantity();
            $amount = Utility::bcabs($transaction->getRawAmount(), $scale);
            $quantity = Utility::bcabs($transaction->getRawQuantity(), $scale);

            // echo $transaction->type . ' = amount: ' . $amount . ', quantity: ' . $quantity . ', date: ' . $transaction->date . PHP_EOL;

            if ($transaction->getTypeValue() === 'buy') {
                // "Hanterar" makulerade köptransaktioner
                if ($transaction->getRawAmount() > 0 && $transaction->getRawQuantity() < 0) {
                    Logger::getInstance()->addWarning("Buy transaction with negative quantity: {$transaction->getRawQuantity()} for {$transaction->getName()} ({$transaction->getIsin()}) [{$transaction->getDateString()}]");

                    $amount = bcsub("0", $amount, $scale); // Gör $amount negativ
                    $quantity = bcsub("0", $quantity, $scale); // Gör $quantity negativ
                }


                // Lägg till köpkostnad och öka antalet aktier
                $totalCost = bcadd($totalCost, $amount, $scale);
                $totalQuantity = bcadd((string) $totalQuantity, $quantity, $scale);
            } elseif ($transaction->getTypeValue() === 'sell') {
                // Leta efter makulerade säljtransaktioner
                if ($transaction->getRawAmount() < 0 && $transaction->getRawQuantity() > 0) {
                    Logger::getInstance()->addWarning("Sell transaction with negative amount: {$transaction->getRawAmount()} for {$transaction->getName()} ({$transaction->getIsin()}) [{$transaction->getDateString()}]");
                }

                // Endast räkna kapitalvinst om det finns köpta aktier att sälja
                if (bccomp((string) $totalQuantity, '0', $scale) > 0 && bccomp((string) $totalQuantity, $quantity, $scale) >= 0) {
                    $costPerShare = bcdiv($totalCost, (string) $totalQuantity, $scale);
                    $sellCost = bcmul($costPerShare, $quantity, $scale);

                    // Räkna ut kapitalvinsten för de sålda aktierna
                    $gain = bcsub($amount, $sellCost, $scale);
                    $realizedGain = bcadd($realizedGain, $gain, $scale);

                    // Minska den totala kostnaden och antalet aktier
                    $totalCost = bcsub($totalCost, $sellCost, $scale);
                    $totalQuantity = bcsub((string) $totalQuantity, $quantity, $scale);
                }
            } elseif ($transaction->getTypeValue() === 'share_split') {
                if ($totalQuantity != 0) {
                    $totalQuantity += $transaction->getRawQuantity();
                }
            }
        }

        if (Utility::isNearlyZero($totalCost)) {
            $totalCost = '0.0';
        }

        // Om det inte finns några aktier kvar så kan vi anta att det inte finns något anskaffningsvärde kvar.
        if ($totalCost != 0 && ($actualQuantity == 0 || Utility::isNearlyZero($totalQuantity))) {
            Logger::getInstance()->addNotice("No shares left for {$transactions[0]->getName()} ({$transactions[0]->getIsin()}) " . $totalCost);
            $totalCost = '0.0';
        }

        $result = new stdClass();
        $result->remainingCostBase = round(floatval($totalCost), 3);
        $result->realizedGain = round(floatval($realizedGain), 3);
        $result->totalQuantity = round(floatval($totalQuantity), 3);
        $result->actualQuantity = round(floatval($actualQuantity), 3);

        return $result;
    }
}
