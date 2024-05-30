<?php

namespace src\Service\Transaction;

use src\DataStructure\FinancialAsset;
use src\DataStructure\FinancialOverview;
use src\DataStructure\Transaction;
use src\Enum\TransactionType;
use src\Service\Utility;
use src\View\Logger;
use stdClass;

class TransactionMapper
{
    public FinancialOverview $overview;

    public function __construct(FinancialOverview $overview)
    {
        $this->overview = $overview;
    }

    private function handleNonAssetTransactionType(Transaction $transaction): void
    {
        switch ($transaction->getType()) {
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
                Logger::getInstance()->addWarning("Unhandled transaction type: '{$transaction->getTypeValue()}' in '{$transaction->getName()}' ({$transaction->getIsin()}) [{$transaction->getDateString()}] from bank: {$transaction->getBankValue()}");
                break;

        }
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
            $isin = $transaction->getIsin();
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

            if (!in_array($transaction->getBankValue(), array_keys($asset->bankAccounts))) {
                $asset->bankAccounts[$transaction->getBankValue()] = [];
            }
            if (!in_array($transaction->getAccount(), $asset->bankAccounts[$transaction->getBankValue()])) {
                $asset->bankAccounts[$transaction->getBankValue()][] = $transaction->getAccount();
            }
            if (!in_array($transaction->getName(), $asset->transactionNames)) {
                $asset->transactionNames[] = $transaction->getName();
            }

            // Only add actual assets.
            if (!$asset->name) {
                $asset->name = $transaction->getName();
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
        switch ($transaction->getType()) {
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
                $asset->addCurrentNumberOfShares($transaction->getRawQuantity());
                break;
            case TransactionType::SHARE_TRANSFER:
                $asset->addCurrentNumberOfShares($transaction->getRawQuantity());
                break;
            case TransactionType::FOREIGN_WITHHOLDING_TAX:
                $this->handleForeignWithholdingTaxTransaction($asset, $transaction);
                break;
            case TransactionType::FEE:
                $this->handleFeeTransaction($asset, $transaction);
                break;
            case TransactionType::OTHER: // TODO: handle this based on the cashflow but give a notice.
            default:
                Logger::getInstance()->addWarning("Unhandled transaction type: '{$transaction->getTypeValue()}' in '{$transaction->getName()}' ({$transaction->getIsin()}) [{$transaction->getDateString()}] from bank: {$transaction->getBankValue()}");
                break;
        }
    }

    private function handleBuyTransaction(FinancialAsset &$asset, Transaction $transaction): void
    {
        $asset->addBuy($transaction->getRawAmount());
        $asset->addCurrentNumberOfShares($transaction->getRawQuantity());
        $asset->addCommissionBuy($transaction->getCommission());

        $this->overview->totalBuyAmount += $transaction->getRawAmount();
        $this->overview->totalBuyCommission += $transaction->getCommission();

        $this->overview->addCashFlow($transaction->getDateString(), $transaction->getRawAmount(), $transaction->getName(), $transaction->getType(), $transaction->getAccount(), $transaction->getBank());
    }

    private function handleSellTransaction(FinancialAsset &$asset, Transaction $transaction): void
    {
        $asset->addSell($transaction->getRawAmount());
        $asset->addCurrentNumberOfShares($transaction->getRawQuantity());
        $asset->addCommissionSell($transaction->getCommission());

        $this->overview->totalSellAmount += $transaction->getRawAmount();
        $this->overview->totalSellCommission += $transaction->getCommission();

        $this->overview->addCashFlow($transaction->getDateString(), $transaction->getRawAmount(), $transaction->getName(), $transaction->getType(), $transaction->getAccount(), $transaction->getBank());
    }

    private function handleDividendTransaction(FinancialAsset &$asset, Transaction $transaction): void
    {
        $asset->addDividend($transaction->getRawAmount());

        $this->overview->totalDividend += $transaction->getRawAmount();
        $this->overview->addCashFlow($transaction->getDateString(), $transaction->getRawAmount(), $transaction->getName(), $transaction->getType(), $transaction->getAccount(), $transaction->getBank());
    }

    private function handleDepositTransaction(Transaction $transaction): void
    {
        $this->overview->depositAmountTotal += $transaction->getRawAmount();
        $this->overview->addCashFlow($transaction->getDateString(), $transaction->getRawAmount(), $transaction->getName(), $transaction->getType(), $transaction->getAccount(), $transaction->getBank());
    }

    private function handleWithdrawalTransaction(Transaction $transaction): void
    {
        $this->overview->withdrawalAmountTotal += $transaction->getRawAmount();
        $this->overview->addCashFlow($transaction->getDateString(), $transaction->getRawAmount(), $transaction->getName(), $transaction->getType(), $transaction->getAccount(), $transaction->getBank());
    }

    private function handleInterestTransaction(Transaction $transaction): void
    {
        if ($transaction->getRawAmount() === null) {
            print_r($transaction);
            exit;
        }
        $this->overview->totalInterest += $transaction->getRawAmount();
        $this->overview->addCashFlow($transaction->getDateString(), $transaction->getRawAmount(), $transaction->getName(), $transaction->getType(), $transaction->getAccount(), $transaction->getBank());
    }

    private function handleTaxTransaction(Transaction $transaction): void
    {
        $this->overview->totalTax += $transaction->getRawAmount();
        $this->overview->addCashFlow($transaction->getDateString(), $transaction->getRawAmount(), $transaction->getName(), $transaction->getType(), $transaction->getAccount(), $transaction->getBank());
    }

    private function handleForeignWithholdingTaxTransaction(FinancialAsset &$asset, Transaction $transaction): void
    {
        if (!empty($asset->isin)) {
            $asset->addForeignWithholdingTax($transaction->getRawAmount());
        }

        $this->overview->totalForeignWithholdingTax += $transaction->getRawAmount();
        $this->overview->addCashFlow($transaction->getDateString(), $transaction->getRawAmount(), $transaction->getName(), $transaction->getType(), $transaction->getAccount(), $transaction->getBank());
    }

    private function handleReturnedForeignWithholdingTaxTransaction(Transaction $transaction): void
    {
        $this->overview->totalReturnedForeignWithholdingTax += $transaction->getRawAmount();
        $this->overview->addCashFlow($transaction->getDateString(), $transaction->getRawAmount(), $transaction->getName(), $transaction->getType(), $transaction->getAccount(), $transaction->getBank());
    }

    private function handleFeeTransaction(?FinancialAsset $asset, Transaction $transaction): void
    {
        if ($asset !== null) {
            $asset->addFee($transaction->getRawAmount());
        }

        $this->overview->totalFee += $transaction->getRawAmount();
        $this->overview->addCashFlow($transaction->getDateString(), $transaction->getRawAmount(), $transaction->getName(), $transaction->getType(), $transaction->getAccount(), $transaction->getBank());
    }
    
    private function handleShareLoanPayoutTransaction(Transaction $transaction): void
    {
        $this->overview->totalShareLoanPayout += $transaction->getRawAmount();
        $this->overview->addCashFlow($transaction->getDateString(), $transaction->getRawAmount(), $transaction->getName(), $transaction->getType(), $transaction->getAccount(), $transaction->getBank());
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
    //         if (!in_array($transaction->getType(), [TransactionType::BUY, TransactionType::SELL])) {
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
