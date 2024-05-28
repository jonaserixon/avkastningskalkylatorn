<?php

namespace src\Libs\Transaction;

use src\DataStructure\FinancialAsset;
use src\DataStructure\FinancialOverview;
use src\DataStructure\Transaction;
use src\DataStructure\TransactionGroup;
use src\Libs\Presenter;
use src\Libs\Utility;
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
     * @param array<string, TransactionGroup> $groupedTransactions
     * @return FinancialAsset[]
     */
    public function addTransactionsToAsset(array $groupedTransactions): array
    {
        $assets = [];
        foreach ($groupedTransactions as $isin => $companyTransactions) {
            $asset = new FinancialAsset();
            $asset->isin = $isin;

            $firstTransactionDate = null;
            $lastTransactionDate = null;
            foreach ((array) $companyTransactions as $groupTransactionType => $transactions) {
                $this->processTransactionType($asset, $groupTransactionType, $transactions);

                $result = $this->getInitialAndLastTransactionDate($transactions, $firstTransactionDate, $lastTransactionDate);
                $firstTransactionDate = $result->firstTransactionDate;
                $lastTransactionDate = $result->lastTransactionDate;
            }

            // Only add actual assets.
            if (!empty($isin)) {
                $asset->name = $asset->transactionNames[0];
                $asset->addTransactions($companyTransactions);

                if (Utility::isNearlyZero($asset->getCurrentNumberOfShares())) {
                    $asset->resetCurrentNumberOfShares();
                }

                if ($firstTransactionDate) {
                    $asset->setFirstTransactionDate($firstTransactionDate);
                }

                if ($lastTransactionDate) {
                    $asset->setLastTransactionDate($lastTransactionDate);
                }

                $assets[] = $asset;
            }

            unset($companyTransactions);
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
                $this->handleBuyTransactionForAsset($asset, $transaction, $transactionAmount);
                break;
            case 'sell':
                $this->handleSellTransactionForAsset($asset, $transaction, $transactionAmount);
                break;
            case 'dividend':
                $this->handleDividendTransactionForAsset($asset, $transaction, $transactionAmount);
                break;
            case 'share_split':
                $asset->addCurrentNumberOfShares($transaction->rawQuantity);
                break;
            case 'share_transfer':
                $asset->addCurrentNumberOfShares($transaction->rawQuantity);
                break;
            case 'deposit':
                $this->handleDepositTransactionForAsset($transaction, $transactionAmount);
                break;
            case 'withdrawal':
                $this->handleWithdrawalTransactionForAsset($transaction, $transactionAmount);
                break;
            case 'interest':
                $this->handleInterestTransactionForAsset($transaction, $transactionAmount);
                break;
            case 'tax':
                $this->handleTaxTransactionForAsset($transaction, $transactionAmount);
                break;
            case 'foreign_withholding_tax':
                $this->handleForeignWithholdingTaxTransactionForAsset($asset, $transaction, $transactionAmount);
                break;
            case 'returned_foreign_withholding_tax':
                $this->handleReturnedForeignWithholdingTaxTransactionForAsset($transaction, $transactionAmount);
                break;
            case 'fee':
                $this->handleFeeTransactionForAsset($asset, $transaction, $transactionAmount);
                break;
            case 'share_loan_payout':
                $this->handleShareLoanPayoutTransactionForAsset($transaction, $transactionAmount);
                break;
            case 'other': // TODO: handle this based on the cashflow but give a notice.
            default:
                echo $this->presenter->redText("Unhandled transaction type: '{$transaction->type}' in '{$transaction->name}' ({$transaction->isin}) [{$transaction->date}] from bank: {$transaction->bank}") . PHP_EOL;
                break;
        }
    }

    private function handleBuyTransactionForAsset(FinancialAsset &$asset, Transaction $transaction, float $transactionAmount): void
    {
        $asset->addBuy($transactionAmount);
        $asset->addCurrentNumberOfShares($transaction->rawQuantity);
        $asset->addCommissionBuy($transaction->commission);

        $this->overview->totalBuyAmount += $transactionAmount;
        $this->overview->totalBuyCommission += $transaction->commission;

        $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);
    }

    private function handleSellTransactionForAsset(FinancialAsset &$asset, Transaction $transaction, float $transactionAmount): void
    {
        $asset->addSell($transactionAmount);
        $asset->addCurrentNumberOfShares($transaction->rawQuantity);
        $asset->addCommissionSell($transaction->commission);

        $this->overview->totalSellAmount += $transactionAmount;
        $this->overview->totalSellCommission += $transaction->commission;

        $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);
    }

    private function handleDividendTransactionForAsset(FinancialAsset &$asset, Transaction $transaction, float $transactionAmount): void
    {
        $asset->addDividend($transactionAmount);

        $this->overview->totalDividend += $transactionAmount;
        $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);
    }

    private function handleDepositTransactionForAsset(Transaction $transaction, float $transactionAmount): void
    {
        $this->overview->depositAmountTotal += $transactionAmount;
        $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);
    }

    private function handleWithdrawalTransactionForAsset(Transaction $transaction, float $transactionAmount): void
    {
        $this->overview->withdrawalAmountTotal += $transactionAmount;
        $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);
    }

    private function handleInterestTransactionForAsset(Transaction $transaction, float $transactionAmount): void
    {
        $this->overview->totalInterest += $transactionAmount;
        $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);
    }

    private function handleTaxTransactionForAsset(Transaction $transaction, float $transactionAmount): void
    {
        $this->overview->totalTax += $transactionAmount;
        $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);
    }

    private function handleForeignWithholdingTaxTransactionForAsset(FinancialAsset &$asset, Transaction $transaction, float $transactionAmount): void
    {
        if (!empty($asset->isin)) {
            $asset->addForeignWithholdingTax($transactionAmount);
        }

        $this->overview->totalForeignWithholdingTax += $transactionAmount;
        $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);
    }

    private function handleReturnedForeignWithholdingTaxTransactionForAsset(Transaction $transaction, float $transactionAmount): void
    {
        $this->overview->totalReturnedForeignWithholdingTax += $transactionAmount;
        $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);
    }

    private function handleFeeTransactionForAsset(FinancialAsset &$asset, Transaction $transaction, float $transactionAmount): void
    {
        if (!empty($asset->isin)) {
            $asset->addFee($transactionAmount);
        }

        $this->overview->totalFee += $transactionAmount;
        $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);
    }
    
    private function handleShareLoanPayoutTransactionForAsset(Transaction $transaction, float $transactionAmount): void
    {
        $this->overview->totalShareLoanPayout += $transactionAmount;
        $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);
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

    /**
     * Get the first and last buy/sell transaction date from a list of transactions.
     * 
     * @param Transaction[] $transactions
     */
    private function getInitialAndLastTransactionDate(array $transactions, ?string $firstTransactionDate, ?string $lastTransactionDate): stdClass
    {
        foreach ($transactions as $transaction) {
            if (!in_array($transaction->type, ['buy', 'sell'])) {
                continue;
            }

            $date = $transaction->date;
            if (!$firstTransactionDate || $date < $firstTransactionDate) {
                $firstTransactionDate = $date;
            }
            if (!$lastTransactionDate || $date > $lastTransactionDate) {
                $lastTransactionDate = $date;
            }
        }

        $result = new stdClass();
        $result->firstTransactionDate = $firstTransactionDate;
        $result->lastTransactionDate = $lastTransactionDate;

        return $result;
    }
}
