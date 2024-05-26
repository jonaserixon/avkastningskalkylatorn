<?php

namespace src\Libs\Transaction;

use src\DataStructure\FinancialAsset;
use src\DataStructure\FinancialOverview;
use src\DataStructure\Transaction;
use src\DataStructure\TransactionGroup;
use src\Libs\Presenter;

class TransactionParser
{
    private Presenter $presenter;
    public FinancialOverview $overview;

    public function __construct(FinancialOverview $overview)
    {
        $this->presenter = new Presenter();
        $this->overview = $overview;
    }

    public function calculateRealizedGains(array $transactions)
    {
        $totalCost = 0;
        $totalShares = 0;
        $realizedGain = 0;

        // TODO: stödjer ej aktiesplittar.

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
            }
        }

        return ['remainingCostBase' => $totalCost, 'realizedGain' => $realizedGain];
    }


    /**
     * @param Transaction[] $transactions
     */
    public function calculateCostBasis(array $transactions): float
    {
        $totalCost = 0;
        $totalShares = 0;

        foreach ($transactions as $transaction) {
            if ($transaction->type === 'buy') {
                $totalCost += abs($transaction->rawAmount);
                $totalShares += abs($transaction->rawQuantity);
            } elseif ($transaction->type === 'sell') {
                if ($totalShares > 0) {
                    $costPerShare = $totalCost / $totalShares;
                    $sellCost = $costPerShare * abs($transaction->rawQuantity);
                    $totalCost -= $sellCost;
                    $totalShares -= abs($transaction->rawQuantity);
                }
            }
        }

        // Sätt kostnadsbasen till 0 om alla aktier har sålts
        if ($totalShares == 0) {
            $totalCost = 0;
        }

        return $totalCost;
    }

    /**
     * @param array<string, TransactionGroup> $groupedTransactions
     * @return FinancialAsset[]
     */
    public function summarizeTransactions(array $groupedTransactions): array
    {
        $assets = [];
        foreach ($groupedTransactions as $isin => $companyTransactions) {
            $asset = new FinancialAsset();
            $asset->isin = $isin;

            $firstTransactionDate = null;
            $lastTransactionDate = null;
            foreach ($companyTransactions as $groupTransactionType => $transactions) {
                $this->processTransactionType($asset, $groupTransactionType, $transactions);

                foreach ($transactions as $transaction) {
                    $date = $transaction->date;
                    if (!$firstTransactionDate || $date < $firstTransactionDate) {
                        $firstTransactionDate = $date;
                    }
                    if (!$lastTransactionDate || $date > $lastTransactionDate) {
                        $lastTransactionDate = $date;
                    }
                }
            }

            $mergedTransactions = array_merge(
                array_map(function($item) { return clone $item; }, $companyTransactions->buy),
                array_map(function($item) { return clone $item; }, $companyTransactions->sell)
            );
            usort($mergedTransactions, function ($a, $b) {
                return strcasecmp($a->date, $b->date);
            });

            if (!empty($mergedTransactions)) {
                $result = $this->calculateRealizedGains($mergedTransactions);
                // $costBasis += $this->calculateCostBasis($mergedTransactions);
                $asset->realizedGainLoss = $result['realizedGain'];
                $asset->costBasis = $result['remainingCostBase'];
            }

            $asset->setFirstTransactionDate($firstTransactionDate);
            $asset->setLastTransactionDate($lastTransactionDate);
            $asset->name = $asset->transactionNames[0];

            if (!empty($asset->isin)) {
                $assets[] = $asset;
            }
        }

        return $assets;
    }

    /**
     * Process grouped transactions for the asset.
     *
     * @param FinancialAsset $asset
     * @param string $groupTransactionType
     * @param Transaction[] $transactions
     */
    private function processTransactionType(FinancialAsset &$asset, string $groupTransactionType, array $transactions): void
    {
        foreach ($transactions as $index => $transaction) {
            $transaction = $transactions[$index];

            $this->updateAssetBasedOnTransactionType($asset, $groupTransactionType, $transaction);


            if (!in_array($transaction->bank, array_keys($asset->bankAccounts))) {
                $asset->bankAccounts[$transaction->bank] = [];
            }
            if (!in_array($transaction->account, $asset->bankAccounts[$transaction->bank])) {
                $asset->bankAccounts[$transaction->bank][] = $transaction->account;
            }

            if (!in_array($transaction->name, $asset->transactionNames)) {
                $asset->transactionNames[] = $transaction->name;
            }
        }
    }

    private function updateAssetBasedOnTransactionType(FinancialAsset &$asset, string $groupTransactionType, Transaction $transaction): void
    {
        $transactionAmount = $transaction->rawAmount;

        switch ($groupTransactionType) {
            case 'buy':
                $asset->addBuy($transactionAmount);
                $asset->addCurrentNumberOfShares($transaction->rawQuantity);
                $asset->addCommissionBuy($transaction->commission);

                $this->overview->totalBuyAmount += $transactionAmount;
                $this->overview->totalBuyCommission += $transaction->commission;

                $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);

                break;
            case 'sell':
                $asset->addSell($transactionAmount);
                $asset->addCurrentNumberOfShares($transaction->rawQuantity);
                $asset->addCommissionSell($transaction->commission);

                $this->overview->totalSellAmount += $transactionAmount;
                $this->overview->totalSellCommission += $transaction->commission;

                $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);

                break;
            case 'dividend':
                $asset->addDividend($transactionAmount);

                $this->overview->totalDividend += $transactionAmount;
                $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);

                break;
            case 'share_split':
                $asset->addCurrentNumberOfShares($transaction->rawQuantity);

                break;
            case 'share_transfer':
                $asset->addCurrentNumberOfShares($transaction->rawQuantity);

                break;
            case 'deposit':
                $this->overview->depositAmountTotal += $transactionAmount;
                $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);

                break;
            case 'withdrawal':
                $this->overview->withdrawalAmountTotal += $transactionAmount;
                $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);

                break;
            case 'interest':
                $this->overview->totalInterest += $transactionAmount;
                $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);

                break;
            case 'tax':
                $this->overview->totalTax += $transactionAmount;
                $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);

                break;
            case 'foreign_withholding_tax':
                if (!empty($asset->isin)) {
                    $asset->addForeignWithholdingTax($transactionAmount);
                }

                $this->overview->totalForeignWithholdingTax += $transactionAmount;
                $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);
                break;
            case 'returned_foreign_withholding_tax':
                $this->overview->totalReturnedForeignWithholdingTax += $transactionAmount;
                $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);

                break;
            case 'fee':
                if (!empty($asset->isin)) {
                    $asset->addFee($transactionAmount);
                }

                $this->overview->totalFee += $transactionAmount;
                $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);

                break;
            default:
                echo $this->presenter->redText("Unknown transaction type: '{$transaction->type}' in {$transaction->name} ({$transaction->isin}) [{$transaction->date}]") . PHP_EOL;
                break;
        }
    }

    /**
     * Group "raw" transactions into categorized lists.
     *
     * @param Transaction[] $transactions Array of transaction objects.
     * @return array<string, TransactionGroup>
     */
    public function groupTransactions(array $transactions): array
    {
        $groupedTransactions = [];
        foreach ($transactions as $transaction) {
            if (!array_key_exists($transaction->isin, $groupedTransactions)) {
                $groupedTransactions[$transaction->isin] = new TransactionGroup();
            }

            if (!property_exists($groupedTransactions[$transaction->isin], $transaction->type)) {
                echo $this->presenter->redText("Unknown transaction type: '{$transaction->type}' in {$transaction->name} ({$transaction->isin}) [{$transaction->date}]") . PHP_EOL;
                continue;
            }

            $groupedTransactions[$transaction->isin]->{$transaction->type}[] = $transaction;
        }

        return $groupedTransactions;
    }

    protected function isNonSwedishIsin(string $isin): bool
    {
        return !str_starts_with($isin, 'SE');
    }
}
