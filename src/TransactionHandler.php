<?php

class TransactionHandler
{
    /**
     * @param Transaction[] $transactions
     */
    public function getTransactionsOverview(array $transactions)
    {
        $groupedTransactions = $this->groupTransactions($transactions);
        $summaries = $this->summarizeTransactions($groupedTransactions);

        return $summaries;
    }

    /**
     * @param Transaction[] $transactions
     */
    private function groupTransactions(array $transactions): array
    {
        // TODO: förbättra hanteringen av transaktioner som inte ska räknas med här.
        $blackListedTransactionNames = [
            'utdelning',
            'källskatt',
            'avkastningsskatt',
            'riskpremie',
            'uttag',
            'nollställning',
            'överföring',
            'direktinsättning',
            'avgift',
            'fraktionslikvid',
            'preliminär skatt',
            'ränta',
        ];

        $groupedTransactions = [];
        $indexToSkip = null;
        foreach ($transactions as $index => $transaction) {
            if ($indexToSkip === $index) {
                print_r(PHP_EOL . '!!OBS!! ' . $transaction->name . ' innehåller en eller flera aktiesplittar. Dubbelkolla alltid.' . PHP_EOL);
                continue;
            }

            if (in_array(mb_strtolower($transaction->name), $blackListedTransactionNames) || empty($transaction->name)) {
                continue;
            }
            foreach ($blackListedTransactionNames as $blackListedTransactionName) {
                if (str_contains(mb_strtolower($transaction->name), $blackListedTransactionName)) {
                    continue 2;
                }
            }

            if (!array_key_exists($transaction->name, $groupedTransactions)) {
                $groupedTransactions[$transaction->name] = [
                    'buy' => [],
                    'sell' => [],
                    'dividend' => [],
                    'shareSplit' => []
                ];
            }
            switch ($transaction->transactionType) {
                case 'buy':
                    $groupedTransactions[$transaction->name]['buy'][] = $transaction;
                    break;
                case 'sell':
                    $groupedTransactions[$transaction->name]['sell'][] = $transaction;
                    break;
                case 'dividend':
                    $groupedTransactions[$transaction->name]['dividend'][] = $transaction;
                    break;
                case 'other':
                    $nextTransaction = $transactions[$index + 1];

                    if ($transaction->bank === 'avanza' && $nextTransaction->bank === 'avanza') {
                        $shareSplitQuantity = $this->lookForShareSplitsAvanza($transaction, $nextTransaction);

                        if ($shareSplitQuantity) {
                            $transaction->quantity = $shareSplitQuantity;
                            $groupedTransactions[$transaction->name]['shareSplit'][] = $transaction;
                            // $groupedTransactions[$transaction->name]['shareSplit'][] = $nextTransaction;
                            $indexToSkip = $index + 1;
                        }
                    }
                    
                    break;
            }
        }

        return $groupedTransactions;
    }

    /**
     * @param array $groupedTransactions
     * @return TransactionSummary[]
     */
    private function summarizeTransactions(array $groupedTransactions): array
    {
        $summaries = [];
        foreach ($groupedTransactions as $name => $companyTransactions) {
            $summary = new TransactionSummary();

            foreach ($companyTransactions as $transactionType => $transactions) {
                foreach ($transactions as $transaction) {
                    // $transactionAmount = $transaction->price * $transaction->quantity; // Det funkar inte om avanza inte skickar med valutan i exporten
                    $transactionAmount = $transaction->amount;

                    // TODO: Inkludera andra typer av avgifter och skatter. T.ex. ADR och källskatt(?)

                    if ($transactionType === 'buy') {
                        $summary->buyAmountTotal += $transactionAmount;
                        $summary->currentNumberOfShares += $transaction->quantity;
                        $summary->feeAmountTotal += $transaction->fee;
                    } elseif ($transactionType === 'sell') {
                        $summary->sellAmountTotal += $transactionAmount;
                        $summary->currentNumberOfShares -= $transaction->quantity;
                        $summary->feeAmountTotal += $transaction->fee;
                    } elseif ($transactionType === 'dividend') {
                        // $summary->dividendAmountTotal += $price * $quantity;

                        $summary->dividendAmountTotal += $transactionAmount;
                    } elseif ($transactionType === 'shareSplit') {
                        $summary->currentNumberOfShares += $transaction->quantity;
                    } else {
                        throw new Exception('Unknown transaction type: ' . $transactionType);
                    }
                }
            }

            $summary->name = $name;
            $summaries[] = $summary;
        }

        return $summaries;
    }

    private function lookForShareSplitsAvanza(Transaction $currentTransaction, Transaction $nextTransaction): ?int
    {
        if ($currentTransaction->name !== $nextTransaction->name) {
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
