<?php

namespace src\Libs\Transaction;

use src\DataStructure\FinancialAsset;
use src\DataStructure\FinancialOverview;
use src\DataStructure\Transaction;
use src\DataStructure\TransactionGroup;
use src\Libs\Presenter;
use stdClass;

class TransactionParser
{
    private Presenter $presenter;
    public FinancialOverview $overview;

    public function __construct(FinancialOverview $overview)
    {
        $this->presenter = new Presenter();
        $this->overview = $overview;
    }

    /**
     * Calculate realized gains and cost basis for a list of transactions.
     *
     * @param Transaction[] $transactions
     * @return stdClass
     */
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

    public function calculateRealizedGains(array $transactions): stdClass
    {
        $totalCost = '0.0';  // Använd strängar för att behålla precision med bcmath
        $totalShares = '0.0';
        $realizedGain = '0.0';

        $scale = 15;

        foreach ($transactions as $transaction) {
            $amount = $this->bcabs($transaction->rawAmount, $scale);
            $quantity = $this->bcabs($transaction->rawQuantity, $scale);

            // echo $transaction->type . ' = amount: ' . $amount . ', quantity: ' . $quantity . ', date: ' . $transaction->date . PHP_EOL;

            if ($transaction->type === 'buy') {
                // Lägg till köpkostnad och öka antalet aktier
                $totalCost = bcadd($totalCost, $amount, $scale);
                $totalShares = bcadd($totalShares, $quantity, $scale);
            } elseif ($transaction->type === 'sell') {
                // Endast räkna kapitalvinst om det finns köpta aktier att sälja
                if (bccomp($totalShares, '0', $scale) > 0 && bccomp($totalShares, $quantity, $scale) >= 0) {
                    $costPerShare = bcdiv($totalCost, $totalShares, $scale);
                    $sellCost = bcmul($costPerShare, $quantity, $scale);

                    // Räkna ut kapitalvinsten för de sålda aktierna
                    $gain = bcsub($amount, $sellCost, $scale);
                    $realizedGain = bcadd($realizedGain, $gain, $scale);

                    // Minska den totala kostnaden och antalet aktier
                    $totalCost = bcsub($totalCost, $sellCost, $scale);
                    $totalShares = bcsub($totalShares, $quantity, $scale);
                }
            } elseif ($transaction->type === 'share_split') {
                if ($totalShares != 0) {
                    $totalShares += $transaction->rawQuantity;
                }

                // if (bccomp($totalShares, '0', $scale) != 0) {
                //     $totalShares = bcadd($totalShares, $quantity, $scale);
                //     if ($this->isNearlyZero($totalShares)) {
                //         $totalShares = '0.0';
                //     }
                // }
            }
        }

        if ($this->isNearlyZero($totalCost)) {
            $totalCost = '0.0';
        }

        $result = new stdClass();
        $result->remainingCostBase = round($totalCost, 3);
        $result->realizedGain = round($realizedGain, 3);

        // var_dump($result);
        // exit;

        return $result;
    }

    private function bcabs($value, $scale = 15)
    {
        return bccomp($value, '0', $scale) < 0 ? bcmul($value, '-1', $scale) : $value;
    }

    private function formatNumberForBCMath($number) {
        if (is_numeric($number)) {
            return number_format($number, 15, '.', ''); // Konverterar till sträng med 15 decimalers precision
        }
        throw new \InvalidArgumentException("Invalid number format");
    }

    private function isNearlyZero($value, $tolerance = 1e-12)
    {
        return bccomp($this->formatNumberForBCMath(abs($value)), $this->formatNumberForBCMath($tolerance), 15) < 0;
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
                    // TODO: endast kolla på köp och säljtransaktioner.
                    $date = $transaction->date;
                    if (!$firstTransactionDate || $date < $firstTransactionDate) {
                        $firstTransactionDate = $date;
                    }
                    if (!$lastTransactionDate || $date > $lastTransactionDate) {
                        $lastTransactionDate = $date;
                    }
                }
            }

            // Skapa en kopia av transaktionerna här, vi vill inte påverka originaldatat.
            $mergedTransactions = array_merge(
                array_map(function($item) { return clone $item; }, $companyTransactions->buy),
                array_map(function($item) { return clone $item; }, $companyTransactions->sell),
                array_map(function($item) { return clone $item; }, $companyTransactions->share_split)
                // TODO: stödja värdepappersflytt?
            );

            if (!empty($mergedTransactions)) {
                // Viktigt att sortera transaktionerna efter datum för beräkningar.
                usort($mergedTransactions, function ($a, $b) {
                    return strcasecmp($a->date, $b->date);
                });

                $result = $this->calculateRealizedGains($mergedTransactions);
                $asset->realizedGainLoss = $result->realizedGain;
                $asset->costBasis = $result->remainingCostBase;
            }

            $asset->setFirstTransactionDate($firstTransactionDate);
            $asset->setLastTransactionDate($lastTransactionDate);
            $asset->name = $asset->transactionNames[0];

            if ($this->isNearlyZero($asset->getCurrentNumberOfShares())) {
                $asset->setCurrentNumberOfShares(0);
            }

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
            case 'share_loan_payout':
                $this->overview->totalShareLoanPayout += $transactionAmount;
                $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);

                break;
            case 'other': // TODO: handle this based on the cashflow but give notice.
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
