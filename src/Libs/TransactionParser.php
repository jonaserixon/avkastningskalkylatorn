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
        'nollställning',
        'fraktionslikvid',
        'kreditkonto',
        'kapitalmedelskonto'
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
        // $this->overview->lastTransactionDate = $transactions[count($transactions) - 1]->date;

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

            $indexesToSkip = [];
            foreach ($companyTransactions as $groupTransactionType => $transactions) {
                $this->processTransactionType($summary, $groupTransactionType, $transactions, $indexesToSkip);
            }

            $summary->name = $summary->transactionNames[0];
            $summaries[] = $summary;
        }

        return $summaries;
    }

    /**
     * Process grouped transactions for the summary.
     */
    private function processTransactionType(TransactionSummary &$summary, string $groupTransactionType, array $transactions, array &$indexesToSkip): void
    {
        foreach ($transactions as $index => $transaction) {
            $transaction = $transactions[$index];
            $nextTransaction = $transactions[$index + 1] ?? null;

            if ($this->skipTransactionFromSummary($indexesToSkip, $index, $transaction)) {
                echo $this->presenter->blueText("Skipping: {$transaction->name} ({$transaction->isin}) [{$transaction->date}]") . PHP_EOL;
                continue;
            }

            if ($groupTransactionType === 'share_transfer') {
                $this->handleShareTransfer($transaction, $nextTransaction, $summary, $indexesToSkip, $index);
            } else {
                $this->updateSummaryBasedOnTransactionType($summary, $groupTransactionType, $transaction);
            }

            if (!in_array($transaction->name, $summary->transactionNames)) {
                $summary->transactionNames[] = $transaction->name;
            }
        }
    }

    /**
     * Handle share transfers for grouped transactions.
     */
    private function handleShareTransfer(Transaction $transaction, ?Transaction $nextTransaction, TransactionSummary &$summary, array &$indexesToSkip, int $index): void
    {
        // $transactionAmount = round($transaction->price * $transaction->quantity, 2);
        $transactionAmount = abs(round($transaction->rawPrice * $transaction->rawQuantity, 2)); // För att kunna se transaktionen som såld gör vi om värdet till ett positivt tal.

        if (!$nextTransaction) {
            echo $this->presenter->blueText("Värdepappersflytt behandlas som såld för det finns inte några fler sådana transaktioner. {$transaction->name} ({$transaction->isin}) [{$transaction->date}]") . PHP_EOL;

            // Transfers that is missing a transfer after the initial transfers can be seen as sold since this most likely indicated a transfer to another bank.
            $summary->sell += $transactionAmount;
            $summary->currentNumberOfShares += round($transaction->rawQuantity, 2);

            // $summary->currentNumberOfShares -= round($transaction->quantity, 2);

            $this->overview->totalSellAmount += $transactionAmount;
            $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type);
        } elseif ($transaction->type === 'share_transfer' && $nextTransaction->type === 'share_transfer') {
            if ($transaction->isin === $nextTransaction->isin) {
                if ($transaction->bank === 'AVANZA' && $nextTransaction->bank === 'AVANZA') {
                    // Om den nästa transaktionen är av samma typ och samma (fast inverterade) quantity samt gjorda på samma datum så är det förmodligen en intern överföring inom samma bank. skippa dessa.
                    if ($transaction->rawQuantity + $nextTransaction->rawQuantity == 0 && $transaction->date === $nextTransaction->date) {
                        echo $this->presenter->blueText("Intern överföring inom samma bank: {$transaction->name} ({$transaction->isin}) [{$transaction->date}]") . PHP_EOL;

                        $indexesToSkip[$index + 1] = 'share_transfer';
                    } else {
                        // Behandlar den som såld här
                        $summary->sell += $transactionAmount;
                        $summary->currentNumberOfShares += round($transaction->rawQuantity, 2);

                        // $summary->currentNumberOfShares -= round($transaction->quantity, 2);

                        $this->overview->totalSellAmount += $transactionAmount;
                        $this->overview->addCashFlow($transaction->date, $transactionAmount, $transaction->name, $transaction->type);
                    }
                }
            }
        }
    }

    /**
     * Transactions that should be skipped from the transaction summary based on different conditions.
     */
    private function skipTransactionFromSummary(array $indexesToSkip, int $index, Transaction $transaction): bool
    {
        if (isset($indexesToSkip[$index]) && $indexesToSkip[$index] === $transaction->type) {
            return true;
        }

        return false;
    }

    private function updateSummaryBasedOnTransactionType(TransactionSummary &$summary, string $groupTransactionType, Transaction $transaction): void
    {
        // $transactionAmount = $transaction->amount;
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
        $indexesToSkip = [];

        foreach ($transactions as $index => $transaction) {
            if (in_array($index, $indexesToSkip)) {
                continue;
            }

            /*
            if ($this->shouldSkipTransaction($transaction)) {
                continue;
            }
            */

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
                ];
            }

            $this->addTransactionToGroup($groupedTransactions, $transactions, $transaction, $index, $indexesToSkip);
        }

        return $groupedTransactions;
    }

    private function addTransactionToGroup(array &$groupedTransactions, array $transactions, Transaction $transaction, int $index, array &$indexesToSkip): void
    {
        // TODO: other innehåller avgifter etc. som vi vill kunna hantera på något sätt.

        // Om transaktionen är klassad som övrig så vill vi kolla om det finns en transaktion efter den som vi kan använda för att avgöra om det är en aktiesplitt osv.
        if ($transaction->type === 'other' && $transaction->quantity != 0 && $transaction->commission == 0) { // Check if this can be considered a share split.
            $nextIndex = $index + 1;
            if (!isset($transactions[$nextIndex])) {
                return;
            }

            $nextTransaction = $transactions[$nextIndex];
            $this->handleSpecialTransactions($transaction, $nextTransaction, $groupedTransactions, $nextIndex, $indexesToSkip);
            return;
        }

        // TODO
        if ($transaction->type === 'other') {
            if ($this->shouldSkipTransaction($transaction)) {
                return;
            }
        }

        $groupedTransactions[$transaction->isin][$transaction->type][] = $transaction;
    }

    /**
     * Handle share splits for the grouping of transactions.
     */
    private function handleSpecialTransactions(Transaction $transaction, Transaction $nextTransaction, array &$groupedTransactions, int $nextIndex, array &$indexesToSkip): void
    {
        // Avanza strategy
        if ($transaction->bank === 'AVANZA' && $nextTransaction->bank === 'AVANZA') {
            $shareSplitQuantity = $this->lookForShareSplitsAvanza($transaction, $nextTransaction);

            if ($shareSplitQuantity) {
                $transaction->quantity = $shareSplitQuantity;
                $transaction->rawQuantity = $shareSplitQuantity;

                $groupedTransactions[$transaction->isin]['share_split'][] = $transaction;
                $indexesToSkip[] = $nextIndex;

                echo $this->presenter->yellowText('!!OBS!! ' . $transaction->name . ' (ISIN: '. $transaction->isin .') innehåller ev. aktiesplittar. Dubbelkolla alltid.') . PHP_EOL;
            }
        }

        // TODO: hantera aktiesplittar från nordnet.
    }

    private function shouldSkipTransaction(Transaction $transaction): bool
    {
        if (in_array(mb_strtolower($transaction->name), static::BLACKLISTED_TRANSACTION_NAMES) || empty($transaction->name)) {
            return true;
        }

        foreach (static::BLACKLISTED_TRANSACTION_NAMES as $blackListedTransactionName) {
            // If the transaction name contains a blacklisted word and the transaction type is unknown, skip the transaction.
            if (str_contains(mb_strtolower($transaction->name), $blackListedTransactionName) || !TransactionType::tryFrom($transaction->type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the new quantity of shares after a share split.
     */
    private function lookForShareSplitsAvanza(Transaction $currentTransaction, Transaction $nextTransaction): ?float
    {
        if ($currentTransaction->isin !== $nextTransaction->isin) {
            return null;
        }

        if (
            ($currentTransaction->type === 'other' && $nextTransaction->type === 'other') &&
            (empty($currentTransaction->amount) && empty($nextTransaction->amount))
        ) {
            if ($currentTransaction->quantity > $nextTransaction->quantity) {
                return $currentTransaction->quantity - $nextTransaction->quantity;
            } else {
                return $nextTransaction->quantity - $currentTransaction->quantity;
            }
        }

        return null;
    }

    protected function isNonSwedishIsin(string $isin): bool
    {
        return !str_starts_with($isin, 'SE');
    }
}
