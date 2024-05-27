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

        $scale = 15;

        $actualQuantity = 0;
        foreach ($transactions as $transaction) {
            $actualQuantity += $transaction->rawQuantity;
            $amount = $this->bcabs($transaction->rawAmount, $scale);
            $quantity = $this->bcabs($transaction->rawQuantity, $scale);
            // $amount = $this->formatNumberForBCMath($transaction->rawAmount);
            // $quantity = $this->formatNumberForBCMath($transaction->rawQuantity);

            // echo $transaction->type . ' = amount: ' . $amount . ', quantity: ' . $quantity . ', date: ' . $transaction->date . PHP_EOL;

            if ($transaction->type === 'buy') {
                // "Hanterar" makulerade köptransaktioner
                if ($transaction->rawAmount > 0 && $transaction->rawQuantity < 0) {
                    echo $this->presenter->redText("Warning: Buy transaction with negative quantity: {$transaction->rawQuantity} for {$transaction->name} ({$transaction->isin}) [{$transaction->date}]") . PHP_EOL;
                    // $amount = -$amount;
                    // $quantity = -$quantity;
                    $amount = bcsub("0", $amount, $scale); // Gör $amount negativ
                    $quantity = bcsub("0", $quantity, $scale); // Gör $quantity negativ
                }
                // Lägg till köpkostnad och öka antalet aktier
                $totalCost = bcadd($totalCost, $amount, $scale);
                $totalQuantity = bcadd($totalQuantity, $quantity, $scale);
            } elseif ($transaction->type === 'sell') {
                // Leta efter makulerade säljtransaktioner
                if ($transaction->rawAmount < 0 && $transaction->rawQuantity > 0) {
                    echo $this->presenter->redText("Warning: Sell transaction with negative amount: {$transaction->rawAmount} for {$transaction->name} ({$transaction->isin}) [{$transaction->date}]") . PHP_EOL;

                }
                // Endast räkna kapitalvinst om det finns köpta aktier att sälja
                if (bccomp($totalQuantity, '0', $scale) > 0 && bccomp($totalQuantity, $quantity, $scale) >= 0) {
                    $costPerShare = bcdiv($totalCost, $totalQuantity, $scale);
                    $sellCost = bcmul($costPerShare, $quantity, $scale);

                    // Räkna ut kapitalvinsten för de sålda aktierna
                    $gain = bcsub($amount, $sellCost, $scale);
                    $realizedGain = bcadd($realizedGain, $gain, $scale);

                    // Minska den totala kostnaden och antalet aktier
                    $totalCost = bcsub($totalCost, $sellCost, $scale);
                    $totalQuantity = bcsub($totalQuantity, $quantity, $scale);
                }
            } elseif ($transaction->type === 'share_split') {
                if ($totalQuantity != 0) {
                    $totalQuantity += $transaction->rawQuantity;
                }
            }
        }

        if ($this->isNearlyZero($totalCost)) {
            $totalCost = 0;
        }

        // Om det inte finns några aktier kvar så kan vi anta att det inte finns något anskaffningsvärde kvar.
        if ($actualQuantity == 0 || $this->isNearlyZero($totalQuantity)) {
            print("Warning: No shares left for {$transactions[0]->name} ({$transactions[0]->isin})") . PHP_EOL;
            $totalCost = 0;
        }

        $result = new stdClass();
        $result->remainingCostBase = round(floatval($totalCost), 3);
        $result->realizedGain = round(floatval($realizedGain), 3);
        $result->totalQuantity = round(floatval($totalQuantity), 3);
        $result->actualQuantity = round(floatval($actualQuantity), 3);

        return $result;
    }

    private function bcabs(float|string $value, int $scale = 15): float|string
    {
        return bccomp($value, '0', $scale) < 0 ? bcmul($value, '-1', $scale) : $value;
    }

    private function formatNumberForBCMath(float $number): string
    {
        return number_format($number, 15, '.', ''); // Konverterar till sträng med 15 decimalers precision
    }

    private function isNearlyZero(float|string $value, float $tolerance = 1e-12): bool
    {
        return bccomp($this->formatNumberForBCMath(abs($value)), $this->formatNumberForBCMath($tolerance), 15) < 0;
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

            if ($this->isNearlyZero($asset->getCurrentNumberOfShares())) {
                $asset->setCurrentNumberOfShares(0);
            }

            $asset->name = $asset->transactionNames[0];

            // Skapa en kopia av transaktionerna här, vi vill inte påverka originaldatat.
            $mergedTransactions = array_merge(
                array_map(function($item) { return clone $item; }, $companyTransactions->buy),
                array_map(function($item) { return clone $item; }, $companyTransactions->sell),
                array_map(function($item) { return clone $item; }, $companyTransactions->share_split)
            );

            if (!empty($mergedTransactions)) {
                // Viktigt att sortera transaktionerna efter datum inför beräkningar.
                usort($mergedTransactions, function ($a, $b) {
                    return strcasecmp($a->date, $b->date);
                });

                $result = $this->calculateRealizedGains($mergedTransactions);
                $asset->realizedGainLoss = $result->realizedGain;
                $asset->costBasis = $result->remainingCostBase;
            }

            // Temporary solution for Avanzas handling of share transfers from Avanza to another bank(?).
            if (!empty($companyTransactions->share_transfer) && empty($asset->getCurrentNumberOfShares()) && $asset->getBuyAmount() < 0) {
                $shareTransferQuantity = 0;
                $shareTransferAmount = 0;
                $shareTransferTransactions = [];
                foreach ($companyTransactions->share_transfer as $shareTransfer) {
                    $shareTransferAmount += $shareTransfer->rawQuantity * $shareTransfer->rawPrice;
                    $shareTransferQuantity += $shareTransfer->rawQuantity;
                    $shareTransferTransactions[] = $shareTransfer;
                }

                if ($shareTransferQuantity != 0) {
                    // echo $this->presenter->redText("Warning: Share transfer(s) for {$asset->name} needs to be double checked. Amount: " . $shareTransferAmount) . PHP_EOL;
                    $asset->notices[] = "Share transfer(s) for {$asset->name} needs to be double checked. Amount: " . $shareTransferAmount . " (" . round($asset->costBasis + $shareTransferAmount, 3) . ")";
                }
            }

            $asset->setFirstTransactionDate($firstTransactionDate);
            $asset->setLastTransactionDate($lastTransactionDate);

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
                echo $this->presenter->redText("Unhandled transaction type: '{$transaction->type}' in '{$transaction->name}' ({$transaction->isin}) [{$transaction->date}] from bank: {$transaction->bank}") . PHP_EOL;
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
