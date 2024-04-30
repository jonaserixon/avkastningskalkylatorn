<?php

class TransactionHandler
{
    /**
     * @param Transaction[] $transactions
     */
    public function getTransactionOverview(array $transactions)
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
            'överföring mellan egna konton',
            'utdelning',
            'källskatt',
        ];

        $groupedTransactions = [];
        $indexToSkip = null;
        foreach ($transactions as $index => $transaction) {
            if ($indexToSkip === $index) {
                print_r(PHP_EOL . '!!OBS!! ' . $transaction->name . ' innehåller en eller flera aktiesplittar. Dubbelkolla alltid.' . PHP_EOL);
                continue;
            }

            if (in_array(strtolower($transaction->name), $blackListedTransactionNames)) {
                continue;
            }
            foreach ($blackListedTransactionNames as $blackListedTransactionName) {
                if (str_contains(strtolower($transaction->name), $blackListedTransactionName)) {
                    print_r($transaction->name);
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
                case 'Köp':
                    $groupedTransactions[$transaction->name]['buy'][] = $transaction;
                    break;
                case 'Sälj':
                    $groupedTransactions[$transaction->name]['sell'][] = $transaction;
                    break;
                case 'Utdelning':
                    $groupedTransactions[$transaction->name]['dividend'][] = $transaction;
                    break;
                case 'Övrigt':
                    $nextTransaction = $transactions[$index + 1];
                    $shareSplitQuantity = $this->lookForShareSplits($transaction, $nextTransaction);

                    if ($shareSplitQuantity) {
                        $transaction->quantity = $shareSplitQuantity;
                        $groupedTransactions[$transaction->name]['shareSplit'][] = $transaction;
                        // $groupedTransactions[$transaction->name]['shareSplit'][] = $nextTransaction;
                        $indexToSkip = $index + 1;
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

    private function lookForShareSplits(Transaction $currentTransaction, Transaction $nextTransaction): ?int
    {
        if ($currentTransaction->name !== $nextTransaction->name) {
            return null;
        }
        
        if (
            ($currentTransaction->transactionType === 'Övrigt' && $nextTransaction->transactionType === 'Övrigt') &&
            (empty($currentTransaction->price) && empty($nextTransaction->price))
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
