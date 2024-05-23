<?php

namespace src\Libs;

use Exception;
use src\DataStructure\Overview;
use src\DataStructure\Transaction;
use src\DataStructure\TransactionSummary;
use src\Enum\TransactionType;
use src\Libs\Presenter;

class TransactionParser
{
    /**
     * @var string[]
     */
    private const BLACKLISTED_TRANSACTION_NAMES = [
        // 'nollställning',
        // // 'fraktionslikvid',
        // 'kreditkonto',
        // 'kapitalmedelskonto',
        // 'kreditdepån'
    ];

    private Presenter $presenter;

    public Overview $overview;

    public function __construct()
    {
        $this->presenter = new Presenter();
    }

    /**
     * @param Transaction[] $transactions
     * @return TransactionSummary[]
     */
    public function getTransactionsOverview(array $transactions): array
    {
        $this->overview = new Overview();
        $this->overview->firstTransactionDate = $transactions[0]->date;
        $this->overview->lastTransactionDate = $transactions[count($transactions) - 1]->date;

        $groupedTransactions = $this->groupTransactions($transactions);
        $summaries = $this->summarizeTransactions($groupedTransactions);

        if (empty($summaries)) {
            throw new Exception('No transaction file in csv format in the imports directory.');
        }

        // Sort summaries by name for readability.
        usort($summaries, function ($a, $b) {
            return strcasecmp($a->name, $b->name);
        });

        return $summaries;
    }

    private function summarizeTransactions(array $groupedTransactions): array
    {
        $summaries = [];
        foreach ($groupedTransactions as $isin => $companyTransactions) {
            $summary = new TransactionSummary();
            $summary->isin = $isin;

            foreach ($companyTransactions as $groupTransactionType => $transactions) {
                $this->processTransactionType($summary, $groupTransactionType, $transactions);
            }

            $summary->name = $summary->transactionNames[0];
            $summaries[] = $summary;
        }

        return $summaries;
    }

    /**
     * Process grouped transactions for the summary.
     */
    private function processTransactionType(TransactionSummary &$summary, string $groupTransactionType, array $transactions): void
    {
        foreach ($transactions as $index => $transaction) {
            $transaction = $transactions[$index];

            if ($groupTransactionType === 'share_transfer') {
                $summary->currentNumberOfShares += round($transaction->rawQuantity, 2);
            } else {
                $this->updateSummaryBasedOnTransactionType($summary, $groupTransactionType, $transaction);
            }

            if (!in_array($transaction->name, $summary->transactionNames)) {
                $summary->transactionNames[] = $transaction->name;
            }
        }
    }

    private function updateSummaryBasedOnTransactionType(TransactionSummary &$summary, string $groupTransactionType, Transaction $transaction): void
    {
        $transactionAmount = $transaction->rawAmount;

        switch ($groupTransactionType) {
            case 'buy':
                $summary->buy += $transactionAmount;
                $summary->currentNumberOfShares += round($transaction->rawQuantity, 2);
                $summary->commissionBuy += $transaction->commission;

                $this->overview->totalBuyAmount += $transactionAmount;
                $this->overview->totalBuyCommission += $transaction->commission;

                $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type);

                break;
            case 'sell':
                $summary->sell += $transactionAmount;
                $summary->currentNumberOfShares += round($transaction->rawQuantity, 2);
                $summary->commissionSell += $transaction->commission;

                $this->overview->totalSellAmount += $transactionAmount;
                $this->overview->totalSellCommission += $transaction->commission;

                $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type);

                break;
            case 'dividend':
                $summary->dividend += $transactionAmount;

                $this->overview->totalDividend += $transactionAmount;
                $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type);

                break;
            case 'share_split':
                $summary->currentNumberOfShares += round($transaction->rawQuantity, 2);

                break;
            case 'deposit':
                $this->overview->depositAmountTotal += $transactionAmount;
                $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type);

                break;
            case 'withdrawal':
                $this->overview->withdrawalAmountTotal += $transactionAmount;
                $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type);

                break;
            case 'interest':
                $this->overview->totalInterest += $transactionAmount;
                $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type);

                break;
            case 'tax':
                $this->overview->totalTax += $transactionAmount;
                $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type);

                break;
            case 'foreign_withholding_tax':
                $this->overview->totalForeignWithholdingTax += $transactionAmount;
                $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type);
                break;
            case 'returned_foreign_withholding_tax':
                $this->overview->totalReturnedForeignWithholdingTax += $transactionAmount;
                $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type);

                break;
            case 'fee':
                $this->overview->totalFee += $transactionAmount;
                $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type);

                break;
            default:
                echo $this->presenter->redText("Unknown transaction type: '{$transaction->type}' in {$transaction->name} ({$transaction->isin}) [{$transaction->date}]") . PHP_EOL;
                break;
        }
    }

    /**
     * Group "raw" transactions.
     *
     * @param Transaction[] $transactions
     */
    public function groupTransactions(array $transactions): array
    {
        $groupedTransactions = [];
        foreach ($transactions as $transaction) {
            if (!array_key_exists($transaction->isin, $groupedTransactions)) {
                $groupedTransactions[$transaction->isin] = [
                    'buy' => [],
                    'sell' => [],
                    'dividends' => [],
                    'interest' => [],
                    'share_split' => [],
                    'share_transfer' => [],
                    'deposit' => [],
                    'withdrawal' => [],
                    'tax' => [],
                    'other' => [],
                    'foreign_withholding_tax' => [],
                    'fee' => [],
                    'returned_foreign_withholding_tax' => [],
                ];
            }

            $this->addTransactionToGroup($groupedTransactions, $transaction);
        }

        return $groupedTransactions;
    }

    private function addTransactionToGroup(array &$groupedTransactions, Transaction $transaction): void
    {
        // Om transaktionen är klassad som övrig så vill vi kolla om det finns en transaktion efter den som vi kan använda för att avgöra om det är en aktiesplitt osv.
        if ($transaction->type === 'other' && $transaction->rawQuantity != 0 && $transaction->commission == 0) { // Check if this can be considered a share split.
            if ($transaction->bank === 'AVANZA') {
                $groupedTransactions[$transaction->isin]['share_split'][] = $transaction;
                return;
            }
        }

        // TODO
        if ($transaction->type === 'other') {
            if (str_contains(mb_strtolower($transaction->name), 'kapitalmedelskonto')) {
                $groupedTransactions[$transaction->isin]['deposit'][] = $transaction;
                return;
            }

            if (str_contains(mb_strtolower($transaction->name), 'nollställning')) {
                $groupedTransactions[$transaction->isin]['deposit'][] = $transaction;
                return;
            }
        }

        if ($transaction->type === 'deposit') {
            if (str_contains(mb_strtolower($transaction->name), 'kreditdepån')) {
                $groupedTransactions[$transaction->isin]['deposit'][] = $transaction;
                return;
            }
        }

        if ($transaction->type === 'withdrawal') {
            if (str_contains(mb_strtolower($transaction->name), 'kapitalmedelskonto')) {
                $groupedTransactions[$transaction->isin]['withdrawal'][] = $transaction;
                return;
            }

            if (str_contains(mb_strtolower($transaction->name), 'nollställning')) {
                $groupedTransactions[$transaction->isin]['withdrawal'][] = $transaction;
                return;
            }
        }

        $groupedTransactions[$transaction->isin][$transaction->type][] = $transaction;
    }

    protected function isNonSwedishIsin(string $isin): bool
    {
        return !str_starts_with($isin, 'SE');
    }
}
