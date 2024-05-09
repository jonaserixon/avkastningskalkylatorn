<?php

class TransactionHandler
{
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
        'överföring',
        'direktinsättning',
        'avgift',
        'fraktionslikvid',
        'preliminär skatt',
        'ränta',
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

    /**
     * @param Transaction[] $transactions
     */
    private function groupTransactions(array $transactions): array
    {
        $groupedTransactions = [];
        $indexToSkip = null;
        foreach ($transactions as $index => $transaction) {
            if ($indexToSkip === $index) {
                print_r(PHP_EOL . '!!OBS!! ' . $transaction->name . ' (ISIN: '. $transaction->isin .') innehåller en eller flera aktiesplittar. Dubbelkolla alltid.' . PHP_EOL);
                continue;
            }

            if (in_array(mb_strtolower($transaction->name), static::BLACKLISTED_TRANSACTION_NAMES) || empty($transaction->name)) {
                continue;
            }
            foreach (static::BLACKLISTED_TRANSACTION_NAMES as $blackListedTransactionName) {
                if (str_contains(mb_strtolower($transaction->name), $blackListedTransactionName)) {
                    continue 2;
                }
            }

            if (!array_key_exists($transaction->isin, $groupedTransactions)) {
                $groupedTransactions[$transaction->isin] = [
                    'buy' => [],
                    'sell' => [],
                    'dividend' => [],
                    'shareSplit' => []
                ];
            }

            switch ($transaction->transactionType) {
                case 'buy':
                    $groupedTransactions[$transaction->isin]['buy'][] = $transaction;
                    break;
                case 'sell':
                    $groupedTransactions[$transaction->isin]['sell'][] = $transaction;
                    break;
                case 'dividend':
                    $groupedTransactions[$transaction->isin]['dividend'][] = $transaction;
                    break;
                case 'other':
                    if (!isset($transactions[$index + 1])) {
                        break;
                    }
                    $nextTransaction = $transactions[$index + 1];

                    if ($transaction->bank === 'AVANZA' && $nextTransaction->bank === 'AVANZA') {
                        $shareSplitQuantity = $this->lookForShareSplitsAvanza($transaction, $nextTransaction);

                        if ($shareSplitQuantity) {
                            $transaction->quantity = $shareSplitQuantity;

                            $groupedTransactions[$transaction->isin]['shareSplit'][] = $transaction;
                            // $groupedTransactions[$transaction->name]['shareSplit'][] = $nextTransaction;

                            $indexToSkip = $index + 1;
                        }
                    }

                    // TODO: hantera aktiesplittar från nordnet.

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
        foreach ($groupedTransactions as $isin => $companyTransactions) {
            $summary = new TransactionSummary();

            $names = [];
            foreach ($companyTransactions as $transactionType => $transactions) {
                foreach ($transactions as $transaction) {
                    // $transactionAmount = $transaction->price * $transaction->quantity; // Det funkar inte om avanza inte skickar med valutan i exporten
                    $transactionAmount = $transaction->amount;

                    // echo $transaction->date . ': ' . $transaction->transactionType . ': avgift: ' . $transaction->fee . ' SEK' . PHP_EOL;

                    // TODO: Inkludera andra typer av avgifter och skatter. T.ex. ADR och källskatt(?)

                    if ($transactionType === 'buy') {
                        $summary->buyAmountTotal += $transactionAmount;
                        $summary->currentNumberOfShares += $transaction->quantity;
                        $summary->feeAmountTotal += $transaction->fee;
                        $summary->feeBuyAmountTotal += $transaction->fee;
                    } elseif ($transactionType === 'sell') {
                        $summary->sellAmountTotal += $transactionAmount;
                        $summary->currentNumberOfShares -= $transaction->quantity;
                        $summary->feeAmountTotal += $transaction->fee;
                        $summary->feeSellAmountTotal += $transaction->fee;
                    } elseif ($transactionType === 'dividend') {
                        // $summary->dividendAmountTotal += $price * $quantity;

                        $summary->dividendAmountTotal += $transactionAmount;
                    } elseif ($transactionType === 'shareSplit') {
                        $summary->currentNumberOfShares += $transaction->quantity;
                    } else {
                        throw new Exception('Unknown transaction type: ' . $transactionType);
                    }

                    // TODO: Förbättra det här fulhacket...
                    if (!in_array($transaction->name, $names)) {
                        $names[] = $transaction->name;
                    }
                }
            }

            $summary->name = $names[0];
            $summary->isin = $isin;
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
