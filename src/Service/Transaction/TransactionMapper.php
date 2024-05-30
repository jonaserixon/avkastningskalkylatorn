<?php

namespace src\Service\Transaction;

use src\DataStructure\FinancialAsset;
use src\DataStructure\FinancialOverview;
use src\DataStructure\Transaction;
use src\DataStructure\TransactionGroup;
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

            if (!in_array($transaction->getBankValue(), array_keys($asset->bankAccounts))) {
                $asset->bankAccounts[$transaction->getBankValue()] = [];
            }
            if (!in_array($transaction->getAccount(), $asset->bankAccounts[$transaction->getBankValue()])) {
                $asset->bankAccounts[$transaction->getBankValue()][] = $transaction->getAccount();
            }
            if (!in_array($transaction->getName(), $asset->transactionNames)) {
                $asset->transactionNames[] = $transaction->getName();
            }
        }
    }

    private function updateAssetBasedOnTransactionType(FinancialAsset &$asset, string $groupTransactionType, Transaction $transaction): void
    {
        switch ($groupTransactionType) {
            case 'buy':
                $this->handleBuyTransactionForAsset($asset, $transaction);
                break;
            case 'sell':
                $this->handleSellTransactionForAsset($asset, $transaction);
                break;
            case 'dividend':
                $this->handleDividendTransactionForAsset($asset, $transaction);
                break;
            case 'share_split':
                $asset->addCurrentNumberOfShares($transaction->getRawQuantity());
                break;
            case 'share_transfer':
                $asset->addCurrentNumberOfShares($transaction->getRawQuantity());
                break;
            case 'deposit':
                $this->handleDepositTransactionForAsset($transaction);
                break;
            case 'withdrawal':
                $this->handleWithdrawalTransactionForAsset($transaction);
                break;
            case 'interest':
                $this->handleInterestTransactionForAsset($transaction);
                break;
            case 'tax':
                $this->handleTaxTransactionForAsset($transaction);
                break;
            case 'foreign_withholding_tax':
                $this->handleForeignWithholdingTaxTransactionForAsset($asset, $transaction);
                break;
            case 'returned_foreign_withholding_tax':
                $this->handleReturnedForeignWithholdingTaxTransactionForAsset($transaction);
                break;
            case 'fee':
                $this->handleFeeTransactionForAsset($asset, $transaction);
                break;
            case 'share_loan_payout':
                $this->handleShareLoanPayoutTransactionForAsset($transaction);
                break;
            case 'other': // TODO: handle this based on the cashflow but give a notice.
            default:
                Logger::getInstance()->addWarning("Unhandled transaction type: '{$transaction->getTypeValue()}' in '{$transaction->getName()}' ({$transaction->getIsin()}) [{$transaction->getDateString()}] from bank: {$transaction->getBankValue()}");
                break;
        }
    }

    private function handleBuyTransactionForAsset(FinancialAsset &$asset, Transaction $transaction): void
    {
        $asset->addBuy($transaction->getRawAmount());
        $asset->addCurrentNumberOfShares($transaction->getRawQuantity());
        $asset->addCommissionBuy($transaction->getCommission());

        $this->overview->totalBuyAmount += $transaction->getRawAmount();
        $this->overview->totalBuyCommission += $transaction->getCommission();

        $this->overview->addCashFlow($transaction->getDateString(), $transaction->getRawAmount(), $transaction->getName(), $transaction->getType(), $transaction->getAccount(), $transaction->getBank());
    }

    private function handleSellTransactionForAsset(FinancialAsset &$asset, Transaction $transaction): void
    {
        $asset->addSell($transaction->getRawAmount());
        $asset->addCurrentNumberOfShares($transaction->getRawQuantity());
        $asset->addCommissionSell($transaction->getCommission());

        $this->overview->totalSellAmount += $transaction->getRawAmount();
        $this->overview->totalSellCommission += $transaction->getCommission();

        $this->overview->addCashFlow($transaction->getDateString(), $transaction->getRawAmount(), $transaction->getName(), $transaction->getType(), $transaction->getAccount(), $transaction->getBank());
    }

    private function handleDividendTransactionForAsset(FinancialAsset &$asset, Transaction $transaction): void
    {
        $asset->addDividend($transaction->getRawAmount());

        $this->overview->totalDividend += $transaction->getRawAmount();
        $this->overview->addCashFlow($transaction->getDateString(), $transaction->getRawAmount(), $transaction->getName(), $transaction->getType(), $transaction->getAccount(), $transaction->getBank());
    }

    private function handleDepositTransactionForAsset(Transaction $transaction): void
    {
        $this->overview->depositAmountTotal += $transaction->getRawAmount();
        $this->overview->addCashFlow($transaction->getDateString(), $transaction->getRawAmount(), $transaction->getName(), $transaction->getType(), $transaction->getAccount(), $transaction->getBank());
    }

    private function handleWithdrawalTransactionForAsset(Transaction $transaction): void
    {
        $this->overview->withdrawalAmountTotal += $transaction->getRawAmount();
        $this->overview->addCashFlow($transaction->getDateString(), $transaction->getRawAmount(), $transaction->getName(), $transaction->getType(), $transaction->getAccount(), $transaction->getBank());
    }

    private function handleInterestTransactionForAsset(Transaction $transaction): void
    {
        if ($transaction->getRawAmount() === null) {
            print_r($transaction);
            exit;
        }
        $this->overview->totalInterest += $transaction->getRawAmount();
        $this->overview->addCashFlow($transaction->getDateString(), $transaction->getRawAmount(), $transaction->getName(), $transaction->getType(), $transaction->getAccount(), $transaction->getBank());
    }

    private function handleTaxTransactionForAsset(Transaction $transaction): void
    {
        $this->overview->totalTax += $transaction->getRawAmount();
        $this->overview->addCashFlow($transaction->getDateString(), $transaction->getRawAmount(), $transaction->getName(), $transaction->getType(), $transaction->getAccount(), $transaction->getBank());
    }

    private function handleForeignWithholdingTaxTransactionForAsset(FinancialAsset &$asset, Transaction $transaction): void
    {
        if (!empty($asset->isin)) {
            $asset->addForeignWithholdingTax($transaction->getRawAmount());
        }

        $this->overview->totalForeignWithholdingTax += $transaction->getRawAmount();
        $this->overview->addCashFlow($transaction->getDateString(), $transaction->getRawAmount(), $transaction->getName(), $transaction->getType(), $transaction->getAccount(), $transaction->getBank());
    }

    private function handleReturnedForeignWithholdingTaxTransactionForAsset(Transaction $transaction): void
    {
        $this->overview->totalReturnedForeignWithholdingTax += $transaction->getRawAmount();
        $this->overview->addCashFlow($transaction->getDateString(), $transaction->getRawAmount(), $transaction->getName(), $transaction->getType(), $transaction->getAccount(), $transaction->getBank());
    }

    private function handleFeeTransactionForAsset(FinancialAsset &$asset, Transaction $transaction): void
    {
        if (!empty($asset->isin)) {
            $asset->addFee($transaction->getRawAmount());
        }

        $this->overview->totalFee += $transaction->getRawAmount();
        $this->overview->addCashFlow($transaction->getDateString(), $transaction->getRawAmount(), $transaction->getName(), $transaction->getType(), $transaction->getAccount(), $transaction->getBank());
    }
    
    private function handleShareLoanPayoutTransactionForAsset(Transaction $transaction): void
    {
        $this->overview->totalShareLoanPayout += $transaction->getRawAmount();
        $this->overview->addCashFlow($transaction->getDateString(), $transaction->getRawAmount(), $transaction->getName(), $transaction->getType(), $transaction->getAccount(), $transaction->getBank());
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
            if (!array_key_exists($transaction->getIsin(), $groupedTransactions)) {
                $groupedTransactions[$transaction->getIsin()] = new TransactionGroup();
            }

            if (!property_exists($groupedTransactions[$transaction->getIsin()], $transaction->getTypeValue())) {
                Logger::getInstance()->addWarning("Unknown transaction type: '{$transaction->getTypeValue()}' in {$transaction->getName()} ({$transaction->getIsin()}) [{$transaction->getDateString()}]");
                continue;
            }

            $groupedTransactions[$transaction->getIsin()]->{$transaction->getTypeValue()}[] = $transaction;
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
            if (!in_array($transaction->getType(), [TransactionType::BUY, TransactionType::SELL])) {
                continue;
            }

            $date = $transaction->getDateString();
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
