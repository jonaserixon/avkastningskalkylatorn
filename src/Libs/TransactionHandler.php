<?php

namespace src\Libs;

use src\DataStructure\Transaction;
use src\DataStructure\TransactionSummary;
use src\Enum\TransactionType;
use Exception;

class TransactionHandler
{
    private Presenter $presenter;

    public function __construct(Presenter $presenter)
    {
        $this->presenter = $presenter;
    }

    // TODO: förbättra hanteringen av transaktioner som inte ska räknas med här.
    /**
     * @var string[]
     */
    private const BLACKLISTED_TRANSACTION_NAMES = [
        'utdelning',
        'källskatt',
        'avkastningsskatt',
        'riskpremie',
        'uttag',
        'nollställning',
        // 'överföring',
        'direktinsättning',
        'avgift',
        'fraktionslikvid',
        'preliminär skatt',
        'ränta',
        'kreditkonto',
        'återbetalning',
    ];

    /**
     * @param Transaction[] $transactions
     */
    public function getTransactionsOverview(array $transactions)
    {
        $groupedTransactions = $this->groupTransactions($transactions);
        $summaries = $this->summarizeTransactions($groupedTransactions);

        if (empty($summaries)) {
            throw new Exception('No transaction file in csv format in the imports directory.');
        }

        usort($summaries, function($a, $b) {
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
            foreach ($companyTransactions as $transactions) {
                $this->processTransactionType($summary, $transactions, $indexesToSkip);
            }

            $summary->name = $summary->names[0];
            $summaries[] = $summary;
        }

        return $summaries;
    }

    private function processTransactionType(TransactionSummary &$summary, array $transactions, array &$indexesToSkip): void
    {
        foreach ($transactions as $index => $transaction) {
            $transaction = $transactions[$index];
            $nextTransaction = $transactions[$index + 1] ?? null;

            if ($this->skipTransactionFromSummary($indexesToSkip, $index, $transaction)) {
                echo $this->presenter->blueText("Skipping: {$transaction->name} ({$transaction->isin}) [{$transaction->date}]") . PHP_EOL;
                continue;
            }

            if ($transaction->transactionType === 'share_transfer') {
                $this->handleShareTransfer($transaction, $nextTransaction, $summary, $indexesToSkip, $index);
            } else {
                $this->updateSummaryBasedOnTransactionType($summary, $transaction);
            }

            if (!in_array($transaction->name, $summary->names)) {
                $summary->names[] = $transaction->name;
            }
        }
    }

    private function handleShareTransfer(Transaction $transaction, ?Transaction $nextTransaction, TransactionSummary &$summary, array &$indexesToSkip, int $index): void
    {
        if (!$nextTransaction) { // Transfers within the same bank.
            echo $this->presenter->blueText("Värdepappersflytt behandlas som såld för det finns inte några fler sådana transaktioner. {$transaction->name} ({$transaction->isin}) [{$transaction->date}]") . PHP_EOL;
            $summary->sellAmountTotal += round($transaction->price * $transaction->quantity, 2);
            $summary->currentNumberOfShares -= round($transaction->quantity, 2);
        } elseif ($transaction->transactionType === 'share_transfer' && $nextTransaction->transactionType === 'share_transfer') {
            if ($transaction->isin === $nextTransaction->isin) {
                if ($transaction->bank === 'AVANZA' && $nextTransaction->bank === 'AVANZA') {
                    $this->handleAvanzaShareTransfer($transaction, $nextTransaction, $summary, $indexesToSkip, $index);
                } else {
                    print_r($transaction);
                }
            }
        }
    }

    private function skipTransactionFromSummary(array $indexesToSkip, int $index, Transaction $transaction): bool
    {
        if (isset($indexesToSkip[$index]) && $indexesToSkip[$index] === $transaction->transactionType) {
            return true;
        }

        return false;
    }

    private function handleAvanzaShareTransfer(Transaction $transaction, Transaction $nextTransaction, TransactionSummary &$summary, array &$indexesToSkip, int $index): void
    {
        // Om den nästa transaktionen är av samma typ och samma (fast inverterade) quantity samt gjorda på samma datum så är det förmodligen en intern överföring inom samma bank. skippa dessa.
        if ($transaction->rawQuantity + $nextTransaction->rawQuantity == 0 && $transaction->date === $nextTransaction->date) {
            echo $this->presenter->blueText("Intern överföring inom samma bank: {$transaction->name} ({$transaction->isin}) [{$transaction->date}]") . PHP_EOL;

            $indexesToSkip[$index + 1] = 'share_transfer';
        } else {
            // Behandlar den som såld här
            $summary->sellAmountTotal += round($transaction->price * $transaction->quantity, 2);
            $summary->currentNumberOfShares -= round($transaction->quantity, 2);

            if (!in_array($transaction->name, $summary->names)) {
                $summary->names[] = $transaction->name;
            }
        }
    }

    private function updateSummaryBasedOnTransactionType(TransactionSummary &$summary, Transaction $transaction): void
    {
        $transactionAmount = $transaction->amount;

        switch ($transaction->transactionType) {
            case 'buy':
                $summary->buyAmountTotal += $transactionAmount;
                $summary->currentNumberOfShares += round($transaction->quantity, 2);
                $summary->feeAmountTotal += $transaction->fee;
                $summary->feeBuyAmountTotal += $transaction->fee;
                break;
            case 'sell':
                $summary->sellAmountTotal += $transactionAmount;
                $summary->currentNumberOfShares -= round($transaction->quantity, 2);
                $summary->feeAmountTotal += $transaction->fee;
                $summary->feeSellAmountTotal += $transaction->fee;
                break;
            case 'dividend':
                $summary->dividendAmountTotal += $transactionAmount;
                break;
            case 'share_split':
                $summary->currentNumberOfShares += round($transaction->quantity, 2);
                break;
            case 'other':
                // TODO: ???
                break;
            default:
                echo $this->presenter->redText("Unknown transaction type: '{$transaction->transactionType}' in {$transaction->name} ({$transaction->isin}) [{$transaction->date}]") . PHP_EOL;
                break;
        }

        if (!in_array($transaction->name, $summary->names)) {
            $summary->names[] = $transaction->name;
        }
    }

    /**
     * @param Transaction[] $transactions
     */
    private function groupTransactions(array $transactions): array
    {
        $groupedTransactions = [];
        $indexesToSkip = [];

        foreach ($transactions as $index => $transaction) {
            if (in_array($index, $indexesToSkip)) {
                continue;
            }
            
            if ($this->shouldSkipTransaction($transaction)) {
                continue;
            }

            if (!array_key_exists($transaction->isin, $groupedTransactions)) {
                $groupedTransactions[$transaction->isin] = [
                    'buy' => [],
                    'sell' => [],
                    'dividend' => [],
                    'share_split' => [],
                    'share_transfer' => []
                ];
            }

            $this->addTransactionToGroup($groupedTransactions, $transactions, $transaction, $index, $indexesToSkip);
        }

        return $groupedTransactions;
    }

    private function addTransactionToGroup(array &$groupedTransactions, array $transactions, Transaction $transaction, int $index, array &$indexesToSkip): void
    {
        if ($transaction->transactionType === 'other') {
            $nextIndex = $index + 1;
            if (!isset($transactions[$nextIndex])) {
                return;
            }

            $nextTransaction = $transactions[$nextIndex];
            $this->handleSpecialTransactions($transaction, $nextTransaction, $groupedTransactions, $nextIndex, $indexesToSkip);
            return;
        }

        $groupedTransactions[$transaction->isin][$transaction->transactionType][] = $transaction;
    }

    private function handleSpecialTransactions(Transaction $transaction, Transaction $nextTransaction, array &$groupedTransactions, int $nextIndex, array &$indexesToSkip): void
    {
        // Avanza strategy
        if ($transaction->bank === 'AVANZA' && $nextTransaction->bank === 'AVANZA') {
            $shareSplitQuantity = $this->lookForShareSplitsAvanza($transaction, $nextTransaction);

            if ($shareSplitQuantity) {
                $transaction->quantity = $shareSplitQuantity;
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
            if (str_contains(mb_strtolower($transaction->name), $blackListedTransactionName) || !TransactionType::tryFrom($transaction->transactionType)) {
                return true;
            }
        }

        return false;
    }

    private function lookForShareSplitsAvanza(Transaction $currentTransaction, Transaction $nextTransaction): ?float
    {
        if ($currentTransaction->isin !== $nextTransaction->isin) {
            return null;
        }

        if (
            ($currentTransaction->transactionType === 'other' && $nextTransaction->transactionType === 'other') &&
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
}
