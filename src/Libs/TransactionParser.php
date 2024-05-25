<?php

namespace src\Libs;

use Exception;
use src\DataStructure\FinancialAsset;
use src\DataStructure\FinancialOverview;
use src\DataStructure\Transaction;
use src\DataStructure\TransactionGroup;
use src\Libs\Presenter;

class TransactionParser
{
    private Presenter $presenter;
    public FinancialOverview $overview;

    public function __construct()
    {
        $this->presenter = new Presenter();
    }

    /**
     * @param Transaction[] $transactions
     * @return FinancialAsset[]
     */
    public function getFinancialAssets(array $transactions): array
    {
        $this->overview = new FinancialOverview();
        $this->overview->firstTransactionDate = $transactions[0]->date;
        $this->overview->lastTransactionDate = $transactions[count($transactions) - 1]->date;

        $groupedTransactions = $this->groupTransactions($transactions);
        $assets = $this->summarizeTransactions($groupedTransactions);

        if (empty($assets)) {
            throw new Exception('No transaction file in csv format in the imports directory.');
        }

        // Sort assets by name for readability.
        usort($assets, function ($a, $b) {
            return strcasecmp($a->name, $b->name);
        });

        return $assets;
    }

    /**
     * @param array<string, TransactionGroup> $groupedTransactions
     * @return FinancialAsset[]
     */
    private function summarizeTransactions(array $groupedTransactions): array
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

            $asset->firstTransactionDate = $firstTransactionDate;
            $asset->lastTransactionDate = $lastTransactionDate;
            $asset->name = $asset->transactionNames[0];

            if (!empty($asset->isin)) {
                $assets[] = $asset;
            }
        }

        return $assets;
    }

    /**
     * Process grouped transactions for the asset.
     * @param FinancialAsset $asset
     * @param string $groupTransactionType
     * @param Transaction[] $transactions
     */
    private function processTransactionType(FinancialAsset &$asset, string $groupTransactionType, array $transactions): void
    {
        foreach ($transactions as $index => $transaction) {
            $transaction = $transactions[$index];

            $this->updateAssetBasedOnTransactionType($asset, $groupTransactionType, $transaction);

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
                $asset->buy += $transactionAmount;
                $asset->currentNumberOfShares += round($transaction->rawQuantity, 4);
                $asset->commissionBuy += $transaction->commission;

                $this->overview->totalBuyAmount += $transactionAmount;
                $this->overview->totalBuyCommission += $transaction->commission;

                $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);

                break;
            case 'sell':
                $asset->sell += $transactionAmount;
                $asset->currentNumberOfShares += round($transaction->rawQuantity, 4);
                $asset->commissionSell += $transaction->commission;

                $this->overview->totalSellAmount += $transactionAmount;
                $this->overview->totalSellCommission += $transaction->commission;

                $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);

                break;
            case 'dividend':
                $asset->dividend += $transactionAmount;

                $this->overview->totalDividend += $transactionAmount;
                $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);

                break;
            case 'share_split':
                $asset->currentNumberOfShares += round($transaction->rawQuantity, 4);

                break;
            case 'share_transfer':
                $asset->currentNumberOfShares += round($transaction->rawQuantity, 4);

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
                    $asset->foreignWithholdingTax += $transactionAmount;
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
                    $asset->fee += $transactionAmount;
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
