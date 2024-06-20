<?php

namespace src\Service\Transaction;

use src\DataStructure\FinancialAsset;
use src\DataStructure\FinancialOverview;
use src\DataStructure\Transaction;
use src\Enum\TransactionType;
use src\Service\Utility;
use src\View\Logger;

class TransactionMapper
{
    public FinancialOverview $overview;

    public function __construct(FinancialOverview $overview)
    {
        $this->overview = $overview;
    }

    /**
     * @param Transaction[] $transactions
     */
    public function _addTransactionsToAsset(string $isin, string $name, array $transactions): FinancialAsset
    {
        $asset = new FinancialAsset();
        $asset->isin = $isin;
        $asset->name = $name;
        foreach ($transactions as $transaction) {
            $isin = $transaction->isin;

            $this->updateAssetBasedOnTransactionType($asset, $transaction);

            if (!in_array($transaction->getBankName(), array_keys($asset->bankAccounts))) {
                $asset->bankAccounts[$transaction->getBankName()] = [];
            }
            if (!in_array($transaction->account, $asset->bankAccounts[$transaction->getBankName()])) {
                $asset->bankAccounts[$transaction->getBankName()][] = $transaction->account;
            }
            if (!in_array($transaction->name, $asset->transactionNames)) {
                $asset->transactionNames[] = $transaction->name;
            }

            // Only add actual assets.
            if (!$asset->name) {
                $asset->name = $transaction->name;
            }

            $asset->addTransaction($transaction);
        }

        if (Utility::isNearlyZero($asset->getCurrentNumberOfShares())) {
            $asset->resetCurrentNumberOfShares();
        }

        return $asset;
    }

    /**
     * @param Transaction[] $transactions
     * @return FinancialAsset[]
     */
    public function addTransactionsToAsset(array $transactions): array
    {
        // echo memory_get_usage() . PHP_EOL;

        $assets = [];
        foreach ($transactions as $transaction) {
            $isin = $transaction->isin;
            if (!$isin) {
                $this->handleNonAssetTransactionType($transaction);
                continue;
            }

            $asset = $assets[$isin] ?? null;
            if (!$asset) {
                $asset = new FinancialAsset();
                $asset->isin = $isin;
                $assets[$isin] = $asset;
            }

            // $this->processTransactionType($asset, $transactions);
            $this->updateAssetBasedOnTransactionType($asset, $transaction);

            if (!in_array($transaction->getBankName(), array_keys($asset->bankAccounts))) {
                $asset->bankAccounts[$transaction->getBankName()] = [];
            }
            if (!in_array($transaction->account, $asset->bankAccounts[$transaction->getBankName()])) {
                $asset->bankAccounts[$transaction->getBankName()][] = $transaction->account;
            }
            if (!in_array($transaction->name, $asset->transactionNames)) {
                $asset->transactionNames[] = $transaction->name;
            }

            // Only add actual assets.
            if (!$asset->name) {
                $asset->name = $transaction->name;
            }

            $asset->addTransaction($transaction);
        }

        foreach ($assets as $asset) {
            if (Utility::isNearlyZero($asset->getCurrentNumberOfShares())) {
                $asset->resetCurrentNumberOfShares();
            }

            // $result = $this->getInitialAndLastTransactionDate($transactions, $firstTransactionDate, $lastTransactionDate);
            // $firstTransactionDate = $result->firstTransactionDate;
            // $lastTransactionDate = $result->lastTransactionDate;

            // if ($firstTransactionDate) {
            //     $asset->setFirstTransactionDate($firstTransactionDate);
            // }

            // if ($lastTransactionDate) {
            //     $asset->setLastTransactionDate($lastTransactionDate);
            // }
        }

        // echo memory_get_usage() . PHP_EOL;

        return $assets;
    }

    private function updateAssetBasedOnTransactionType(FinancialAsset &$asset, Transaction $transaction): void
    {
        switch ($transaction->type) {
            case TransactionType::BUY:
                $this->handleBuyTransaction($asset, $transaction);
                break;
            case TransactionType::SELL:
                $this->handleSellTransaction($asset, $transaction);
                break;
            case TransactionType::DIVIDEND:
                $this->handleDividendTransaction($asset, $transaction);
                break;
            case TransactionType::SHARE_SPLIT:
                if ($transaction->rawQuantity !== null) {
                    $asset->addCurrentNumberOfShares($transaction->rawQuantity);
                }
                break;
            case TransactionType::SHARE_TRANSFER:
                if ($transaction->rawQuantity !== null) {
                    $asset->addCurrentNumberOfShares($transaction->rawQuantity);
                }
                break;
            case TransactionType::FOREIGN_WITHHOLDING_TAX:
                $this->handleForeignWithholdingTaxTransaction($asset, $transaction);
                break;
            case TransactionType::FEE:
                $this->handleFeeTransaction($asset, $transaction);
                break;
            case TransactionType::OTHER: // TODO: handle this based on the cashflow but give a notice.
            default:
                Logger::getInstance()->addWarning("Unhandled transaction type: '{$transaction->getTypeName()}' in '{$transaction->name}' ({$transaction->isin}) [{$transaction->getDateString()}] from bank: {$transaction->getBankName()}");
                break;
        }
    }

    public function handleNonAssetTransactionType(Transaction $transaction): void
    {
        switch ($transaction->type) {
            case TransactionType::DEPOSIT:
                $this->handleDepositTransaction($transaction);
                break;
            case TransactionType::WITHDRAWAL:
                $this->handleWithdrawalTransaction($transaction);
                break;
            case TransactionType::INTEREST:
                $this->handleInterestTransaction($transaction);
                break;
            case TransactionType::TAX:
                $this->handleTaxTransaction($transaction);
                break;
            case TransactionType::RETURNED_FOREIGN_WITHHOLDING_TAX:
                $this->handleReturnedForeignWithholdingTaxTransaction($transaction);
                break;
            case TransactionType::FEE:
                $this->handleFeeTransaction(null, $transaction);
                break;
            case TransactionType::SHARE_LOAN_PAYOUT:
                $this->handleShareLoanPayoutTransaction($transaction);
                break;
            case TransactionType::OTHER: // TODO: handle this based on the cashflow but give a notice.
            default:
                Logger::getInstance()->addWarning("Unhandled transaction type: '{$transaction->getTypeName()}' in '{$transaction->name}' ({$transaction->isin}) [{$transaction->getDateString()}] from bank: {$transaction->getBankName()}");
                break;
        }
    }

    private function handleBuyTransaction(FinancialAsset &$asset, Transaction $transaction): void
    {
        if ($transaction->rawAmount !== null) {
            $asset->addBuy($transaction->rawAmount);
            $this->overview->totalBuyAmount += $transaction->rawAmount;
            $this->overview->addCashFlow($transaction->getDateString(), $transaction->rawAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);
        }
        if ($transaction->rawQuantity !== null) {
            $asset->addCurrentNumberOfShares($transaction->rawQuantity);
        }
        if ($transaction->commission !== null) {
            $asset->addCommissionBuy($transaction->commission);
            $this->overview->totalBuyCommission += $transaction->commission;
        }
    }

    private function handleSellTransaction(FinancialAsset &$asset, Transaction $transaction): void
    {
        if ($transaction->rawAmount !== null) {
            $asset->addSell($transaction->rawAmount);
            $this->overview->totalSellAmount += $transaction->rawAmount;
            $this->overview->addCashFlow($transaction->getDateString(), $transaction->rawAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);
        }
        if ($transaction->rawQuantity !== null) {
            $asset->addCurrentNumberOfShares($transaction->rawQuantity);
        }
        if ($transaction->commission !== null) {
            $asset->addCommissionSell($transaction->commission);
            $this->overview->totalSellCommission += $transaction->commission;
        }
    }

    private function handleDividendTransaction(FinancialAsset &$asset, Transaction $transaction): void
    {
        if ($transaction->rawAmount !== null) {
            $asset->addDividend($transaction->rawAmount);
            $this->overview->totalDividend += $transaction->rawAmount;
            $this->overview->addCashFlow($transaction->getDateString(), $transaction->rawAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);
        }
    }

    private function handleDepositTransaction(Transaction $transaction): void
    {
        if ($transaction->rawAmount !== null) {
            $this->overview->depositAmountTotal += $transaction->rawAmount;
            $this->overview->addCashFlow($transaction->getDateString(), $transaction->rawAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);
        }
    }

    private function handleWithdrawalTransaction(Transaction $transaction): void
    {
        if ($transaction->rawAmount !== null) {
            $this->overview->withdrawalAmountTotal += $transaction->rawAmount;
            $this->overview->addCashFlow($transaction->getDateString(), $transaction->rawAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);
        }
    }

    private function handleInterestTransaction(Transaction $transaction): void
    {
        if ($transaction->rawAmount !== null) {
            $this->overview->totalInterest += $transaction->rawAmount;
            $this->overview->addCashFlow($transaction->getDateString(), $transaction->rawAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);
        }
    }

    private function handleTaxTransaction(Transaction $transaction): void
    {
        if ($transaction->rawAmount !== null) {
            $this->overview->totalTax += $transaction->rawAmount;
            $this->overview->addCashFlow($transaction->getDateString(), $transaction->rawAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);
        }
    }

    private function handleForeignWithholdingTaxTransaction(FinancialAsset &$asset, Transaction $transaction): void
    {
        if ($transaction->rawAmount !== null) {
            if (!empty($asset->isin)) {
                $asset->addForeignWithholdingTax($transaction->rawAmount);
            }
    
            $this->overview->totalForeignWithholdingTax += $transaction->rawAmount;
            $this->overview->addCashFlow($transaction->getDateString(), $transaction->rawAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);
        }
    }

    private function handleReturnedForeignWithholdingTaxTransaction(Transaction $transaction): void
    {
        if ($transaction->rawAmount !== null) {
            $this->overview->totalReturnedForeignWithholdingTax += $transaction->rawAmount;
            $this->overview->addCashFlow($transaction->getDateString(), $transaction->rawAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);
        }
    }

    private function handleFeeTransaction(?FinancialAsset $asset, Transaction $transaction): void
    {
        if ($transaction->rawAmount !== null) {
            if ($asset !== null) {
                $asset->addFee($transaction->rawAmount);
            }
    
            $this->overview->totalFee += $transaction->rawAmount;
            $this->overview->addCashFlow($transaction->getDateString(), $transaction->rawAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);
        }
    }
    
    private function handleShareLoanPayoutTransaction(Transaction $transaction): void
    {
        if ($transaction->rawAmount !== null) {
            $this->overview->totalShareLoanPayout += $transaction->rawAmount;
            $this->overview->addCashFlow($transaction->getDateString(), $transaction->rawAmount, $transaction->name, $transaction->type, $transaction->account, $transaction->bank);
        }
    }

    protected function isNonSwedishIsin(string $isin): bool
    {
        return !str_starts_with($isin, 'SE');
    }

    // /**
    //  * Get the first and last buy/sell transaction date from a list of transactions.
    //  * 
    //  * @param Transaction[] $transactions
    //  */
    // private function getInitialAndLastTransactionDate(array $transactions, ?string $firstTransactionDate, ?string $lastTransactionDate): stdClass
    // {
    //     foreach ($transactions as $transaction) {
    //         if (!in_array($transaction->type, [TransactionType::BUY, TransactionType::SELL])) {
    //             continue;
    //         }

    //         $date = $transaction->getDateString();
    //         if (!$firstTransactionDate || $date < $firstTransactionDate) {
    //             $firstTransactionDate = $date;
    //         }
    //         if (!$lastTransactionDate || $date > $lastTransactionDate) {
    //             $lastTransactionDate = $date;
    //         }
    //     }

    //     $result = new stdClass();
    //     $result->firstTransactionDate = $firstTransactionDate;
    //     $result->lastTransactionDate = $lastTransactionDate;

    //     return $result;
    // }
}
