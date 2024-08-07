<?php

declare(strict_types=1);

namespace Avk\Service\Performance;

use Avk\DataStructure\AssetPerformance;
use Avk\DataStructure\FinancialAsset;
use Avk\DataStructure\FinancialOverview;
use Avk\DataStructure\Transaction;
use Avk\Enum\Bank;
use Avk\Enum\TransactionType;
use Avk\Service\FileManager\CsvProcessor\StockPrice;
use Avk\Service\Utility;
use Avk\View\Logger;
use Exception;
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
     */
    public function calculate(array $assets, FinancialOverview $overview): stdClass
    {
        $currentHoldingsMissingPricePerShare = [];
        $filteredAssets = [];
        foreach ($assets as $asset) {
            if ($this->filterCurrentHoldings && (int) $asset->getCurrentNumberOfShares() <= 0) {
                continue;
            }

            $mergedTransactions = array_merge(
                array_map(function (Transaction $item): Transaction { return clone $item; }, $asset->getTransactionsByType(TransactionType::BUY)),
                array_map(function (Transaction $item): Transaction { return clone $item; }, $asset->getTransactionsByType(TransactionType::SELL)),
                array_map(function (Transaction $item): Transaction { return clone $item; }, $asset->getTransactionsByType(TransactionType::SHARE_SPLIT))
            );

            if (!empty($mergedTransactions)) {
                // Viktigt att sortera transaktionerna efter datum inför beräkningar.
                usort($mergedTransactions, function (Transaction $a, Transaction $b): int {
                    return $a->date <=> $b->date;
                });

                $result = $this->calculateRealizedGains($mergedTransactions);
                $asset->setRealizedGainLoss($result->realizedGain);
                $asset->setCostBasis($result->remainingCostBase);
            }
            unset($mergedTransactions);

            // Temporary solution for Avanzas handling of share transfers from Avanza to another bank(?).
            if (!empty($asset->hasTransactionOfType(TransactionType::SHARE_TRANSFER)) && empty($asset->getCurrentNumberOfShares()) && $asset->getBuyAmount() < 0) {
                $shareTransferQuantity = 0;
                $shareTransferAmount = 0;
                foreach ($asset->getTransactionsByType(TransactionType::SHARE_TRANSFER) as $shareTransfer) {
                    $shareTransferAmount += $shareTransfer->rawQuantity * $shareTransfer->rawPrice;
                    $shareTransferQuantity += $shareTransfer->rawQuantity;
                }

                if ($shareTransferQuantity !== 0.0) {
                    Logger::getInstance()->addNotice("Share transfer(s) for {$asset->name} needs to be double checked. Amount: " . $shareTransferAmount . " (" . round($asset->getCostBasis() + $shareTransferAmount, 3) . ")");
                }
            }

            if (!empty($asset->isin)) {
                $currentPricePerShare = $this->stockPrice->getCurrentPriceByIsin($asset->isin);

                if ($currentPricePerShare && (int) $asset->getCurrentNumberOfShares() > 0) {
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

                    $asset->setCurrentPricePerShare($currentPricePerShare);
                    $asset->setCurrentValueOfShares($currentValueOfShares);
                    $asset->setUnrealizedGainLoss($asset->getCurrentValueOfShares() - $asset->getCostBasis());

                    $filteredAssets[] = $asset;

                    continue;
                }

                // $asset->assetReturn = $this->calculateTotalReturnForAsset($asset);

                $isMissingPricePerShare = (int) $asset->getCurrentNumberOfShares() > 0 && !$currentPricePerShare;
                if ($isMissingPricePerShare) {
                    $currentHoldingsMissingPricePerShare[] = $asset->name . ' (' . $asset->isin . ')';
                }
            }
        }

        // Important for calculations etc.
        usort($overview->cashFlows, function (Transaction $a, Transaction $b): int {
            return strtotime($a->getDateString()) <=> strtotime($b->getDateString());
        });

        $result = new stdClass();
        $result->currentHoldingsMissingPricePerShare = $currentHoldingsMissingPricePerShare;

        if ($this->filterCurrentHoldings) {
            $result->assets = $filteredAssets;
        } else {
            $result->assets = $assets;
        }

        $overview->setPerformance($this->calculateTotalReturnForFinancialOverview($overview, $result->assets));

        $result->overview = $overview;
        $this->calculateCurrentHoldingsWeighting($result->overview, $result->assets);

        return $result;
    }

    /**
     * Calculate the current holdings weighting for each asset.
     * @param FinancialOverview $overview
     * @param FinancialAsset[] $assets
     */
    private function calculateCurrentHoldingsWeighting(FinancialOverview $overview, array $assets): void
    {
        foreach ($assets as $asset) {
            if ($asset->getCurrentValueOfShares() > 0) {
                $weighting = $asset->getCurrentValueOfShares() / $overview->totalCurrentHoldings * 100;
                $overview->currentHoldingsWeighting[$asset->name] = round($weighting, 4);
            }
        }
    }

    /*
    private function calculateTotalReturnForAsset(FinancialAsset $asset): AssetReturn
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
    */

    /**
     * @param FinancialAsset[] $assets
     */
    private function calculateTotalReturnForFinancialOverview(FinancialOverview $overview, array $assets): AssetPerformance
    {
        // $totalReturnInclFees = 0;
        // $totalReturnInclFees += $overview->totalSellAmount;
        // $totalReturnInclFees += $overview->totalDividend;
        // $totalReturnInclFees += $overview->totalCurrentHoldings;
        // $totalReturnInclFees += $overview->totalBuyAmount;
        // $totalReturnInclFees += $overview->totalFee;
        // $totalReturnInclFees += $overview->totalTax;
        // $totalReturnInclFees += $overview->totalInterest;
        // $totalReturnInclFees += $overview->totalForeignWithholdingTax;
        // $totalReturnInclFees += $overview->totalReturnedForeignWithholdingTax;

        $performance = new AssetPerformance();
        // $result->absolutePerformance = $totalReturnInclFees;

        $totalRealizedCapitalGainLoss = 0;
        $totalUnrealizedCapitalGainLoss = 0;
        foreach ($assets as $asset) {
            $totalRealizedCapitalGainLoss += $asset->getRealizedGainLoss();
            $totalUnrealizedCapitalGainLoss += $asset->getUnrealizedGainLoss();
        }

        $performance->realizedGainLoss = $totalRealizedCapitalGainLoss;
        $performance->unrealizedGainLoss = $totalUnrealizedCapitalGainLoss;

        /*
        $cashFlowArray = [];
        foreach ($overview->cashFlows as $cashFlow) {
            $amount = $cashFlow->rawAmount;
            if (!in_array($cashFlow->type, [
                TransactionType::DEPOSIT,
                TransactionType::WITHDRAWAL,
                TransactionType::DIVIDEND,
                // TransactionType::CURRENT_HOLDING,
                TransactionType::FEE,
                TransactionType::FOREIGN_WITHHOLDING_TAX,
                TransactionType::RETURNED_FOREIGN_WITHHOLDING_TAX
            ])) {
                continue;
            }
            $cashFlowArray[] = [
                'date' => $cashFlow->getDateString(),
                'amount' => $amount,
                'type' => $cashFlow->getTypeName()
            ];
        }

        $averageCapital = $this->calculateAverageInvestedCapital($cashFlowArray, new DateTime());
        echo "Average Invested Capital: " . number_format($averageCapital, 2) . PHP_EOL;
        exit;
        */

        $xirr = $this->calculateXIRR($overview->cashFlows, 'portfolio');
        if ($xirr === null) {
            Logger::getInstance()->addNotice("XIRR did not converge for holding cash flows");
        } else {
            $performance->xirr = $xirr * 100;
        }

        return $performance;
    }

    /**
     * Calculate realized gains and cost basis for a list of transactions.
     *
     * @param Transaction[] $transactions
     */
    private function calculateRealizedGains(array $transactions): stdClass
    {
        $totalCost = '0.0';
        $totalQuantity = '0.0';
        $realizedGain = '0.0';
        $actualQuantity = 0;

        $scale = 15;

        foreach ($transactions as $transaction) {
            if ($transaction->rawAmount === null || $transaction->rawQuantity === null) {
                continue;
            }

            $actualQuantity += $transaction->rawQuantity;
            $amount = Utility::bcabs($transaction->rawAmount, $scale);
            $quantity = Utility::bcabs($transaction->rawQuantity, $scale);

            // echo $transaction->type . ' = amount: ' . $amount . ', quantity: ' . $quantity . ', date: ' . $transaction->date . PHP_EOL;

            if ($transaction->getTypeName() === 'buy') {
                // "Hanterar" makulerade köptransaktioner
                if ($transaction->rawAmount > 0 && $transaction->rawQuantity < 0) {
                    Logger::getInstance()->addWarning("Buy transaction with negative quantity: {$transaction->rawQuantity} for {$transaction->name} ({$transaction->isin}) [{$transaction->getDateString()}]");

                    $amount = bcsub("0", $amount, $scale); // Gör $amount negativ
                    $quantity = bcsub("0", $quantity, $scale); // Gör $quantity negativ
                }


                // Lägg till köpkostnad och öka antalet aktier
                $totalCost = bcadd($totalCost, $amount, $scale);
                $totalQuantity = bcadd((string) $totalQuantity, $quantity, $scale);
            } elseif ($transaction->getTypeName() === 'sell') {
                // Leta efter makulerade säljtransaktioner
                if ($transaction->rawAmount < 0 && $transaction->rawQuantity > 0) {
                    Logger::getInstance()->addWarning("Sell transaction with negative amount: {$transaction->rawAmount} for {$transaction->name} ({$transaction->isin}) [{$transaction->getDateString()}]");
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
            } elseif ($transaction->getTypeName() === 'share_split') {
                if ($totalQuantity != 0) { // @phan-suppress-current-line PhanPluginComparisonNotStrictForScalar
                    $totalQuantity += $transaction->rawQuantity;
                }
            }
        }

        if (Utility::isNearlyZero($totalCost)) {
            $totalCost = '0.0';
        }

        // Om det inte finns några aktier kvar så kan vi anta att det inte finns något anskaffningsvärde kvar.
        if ($totalCost != 0 && ($actualQuantity == 0 || Utility::isNearlyZero($totalQuantity))) { // @phan-suppress-current-line PhanPluginComparisonNotStrictForScalar
            Logger::getInstance()->addNotice("No shares left for {$transactions[0]->name} ({$transactions[0]->isin}) " . $totalCost);
            $totalCost = '0.0';
        }

        $result = new stdClass();
        $result->remainingCostBase = round(floatval($totalCost), 3);
        $result->realizedGain = round(floatval($realizedGain), 3);
        $result->totalQuantity = round(floatval($totalQuantity), 3);
        $result->actualQuantity = round(floatval($actualQuantity), 3);

        return $result;
    }

    /*
    private function calculateAverageInvestedCapital(array $transactions, DateTime $endDate): float
    {
        $totalWeightedCapital = 0;
        $previousDate = null;
        $currentCapital = 0;

        foreach ($transactions as $transaction) {
            $transactionDate = new DateTime($transaction['date']);
            if ($previousDate !== null) {
                $daysInvested = $previousDate->diff($transactionDate)->days;
                $totalWeightedCapital += $currentCapital * $daysInvested;
            }
            // Uppdatera kapitalet med nuvarande transaktion
            $currentCapital += $transaction['amount'];
            $previousDate = $transactionDate;
        }

        // Hantera perioden från sista transaktionen till slutdatumet
        if ($previousDate !== null) {
            $daysInvested = $previousDate->diff($endDate)->days;
            $totalWeightedCapital += $currentCapital * $daysInvested;
        }

        // Totala antal dagar från första transaktionsdatum till slutdatumet
        $firstTransactionDate = new DateTime($transactions[0]['date']);
        $totalDays = $firstTransactionDate->diff($endDate)->days;

        if ($totalDays > 0) {
            return $totalWeightedCapital / $totalDays;
        } else {
            return 0; // Förhindra division med noll
        }
    }
    */

    /**
     * Calculate the XIRR (Internal Rate of Return) for a list of cash flows.
     *
     * @param Transaction[] $cashFlows
     * @param string $method portfolio|holding
     */
    protected function calculateXIRR(array $cashFlows, string $method): ?float
    {
        // TODO: improve this part
        $transactions = [];
        if ($method === 'portfolio') {
            foreach ($cashFlows as $cashFlow) {
                $amount = $cashFlow->rawAmount;
                if ($amount === null) {
                    Logger::getInstance()->addNotice("Null amount for cash flow on {$cashFlow->getDateString()}");
                    continue;
                }
                if ($cashFlow->getTypeName() === 'deposit') {
                    $amount *= -1;
                } elseif ($cashFlow->getTypeName() === 'withdrawal') {
                    $amount = abs($amount);
                }
                if (!in_array($cashFlow->type, [
                    TransactionType::DEPOSIT,
                    TransactionType::WITHDRAWAL,
                    TransactionType::DIVIDEND,
                    TransactionType::CURRENT_HOLDING,
                    TransactionType::FEE,
                    TransactionType::FOREIGN_WITHHOLDING_TAX,
                    TransactionType::RETURNED_FOREIGN_WITHHOLDING_TAX
                ])) {
                    continue;
                }
                $transactions[] = [
                    'date' => $cashFlow->date,
                    'amount' => $amount
                ];
            }
        } elseif ($method === 'holding') {
            foreach ($cashFlows as $cashFlow) {
                $amount = $cashFlow->rawAmount;
                if (!in_array($cashFlow->type, [
                    TransactionType::BUY,
                    TransactionType::SELL,
                    TransactionType::DIVIDEND,
                    TransactionType::CURRENT_HOLDING,
                    TransactionType::FEE,
                    TransactionType::FOREIGN_WITHHOLDING_TAX
                ])) {
                    continue;
                }
                $transactions[] = [
                    'date' => $cashFlow->date,
                    'amount' => $amount
                ];
            }
        } else {
            throw new Exception('Invalid method for XIRR calculation');
        }

        usort($transactions, function (array $a, array $b): int {
            return $a['date'] <=> $b['date'];
        });

        $minDate = $transactions[0]['date'];

        // NPV (Net Present Value) function
        $npv = function (float $rate) use ($transactions, $minDate): float {
            $sum = 0;
            foreach ($transactions as $transaction) {
                $amount = $transaction['amount'];
                $date = $transaction['date'];
                $days = $minDate->diff($date)->days;
                $sum += $amount / pow(1 + $rate, $days / 365);
            }
            return $sum;
        };

        // Newton-Raphson method to find the root
        $guess = 0.1;
        $tolerance = 0.0001;
        $maxIterations = 100;
        $iteration = 0;

        while ($iteration < $maxIterations) {
            $npvValue = $npv($guess);
            $npvDerivative = ($npv($guess + $tolerance) - $npvValue) / $tolerance;

            // Hantera liten derivata
            if (abs($npvDerivative) < $tolerance) {
                // Justera gissningen lite för att undvika division med noll
                $npvDerivative = $tolerance;
            }

            $newGuess = $guess - $npvValue / $npvDerivative;

            if (abs($newGuess - $guess) < $tolerance) {
                return $newGuess;
            }

            $guess = $newGuess;
            $iteration++;
        }

        // throw new Exception("XIRR did not converge");
        return null;
    }
}
