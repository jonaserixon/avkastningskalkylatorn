<?php

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

    /**
     * @param Transaction[] $transactions
     */
    private function groupTransactions(array $transactions): array
    {
        $groupedTransactions = [];
        $indexToSkip = null;

        foreach ($transactions as $index => $transaction) {
            if ($indexToSkip === $index) {
                continue;
            }
            if (in_array(mb_strtolower($transaction->name), static::BLACKLISTED_TRANSACTION_NAMES) || empty($transaction->name)) {
                continue;
            }
            foreach (static::BLACKLISTED_TRANSACTION_NAMES as $blackListedTransactionName) {
                // If the transaction name contains a blacklisted word and the transaction type is unknown, skip the transaction.
                if (str_contains(mb_strtolower($transaction->name), $blackListedTransactionName) || !TransactionType::tryFrom($transaction->transactionType)) {
                    continue 2;
                }
            }

            if (!array_key_exists($transaction->isin, $groupedTransactions)) {
                $groupedTransactions[$transaction->isin] = [
                    'buy' => [],
                    'sell' => [],
                    'dividend' => [],
                    'shareSplit' => [],
                    'shareTransfer' => []
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

                    // Avanza strategy
                    if ($transaction->bank === 'AVANZA' && $nextTransaction->bank === 'AVANZA') {
                        $shareSplitQuantity = $this->lookForShareSplitsAvanza($transaction, $nextTransaction);

                        if ($shareSplitQuantity) {
                            $transaction->quantity = $shareSplitQuantity;
                            $groupedTransactions[$transaction->isin]['shareSplit'][] = $transaction;

                            $indexToSkip = $index + 1;

                            echo $this->presenter->yellowText('!!OBS!! ' . $transaction->name . ' (ISIN: '. $transaction->isin .') innehåller ev. aktiesplittar. Dubbelkolla alltid.') . PHP_EOL;
                        }
                    }

                    // TODO: hantera aktiesplittar från nordnet.

                    break;
                case 'share_transfer':
                    $groupedTransactions[$transaction->isin]['shareTransfer'][] = $transaction;
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
        // print_r($groupedTransactions);exit;
        $summaries = [];
        foreach ($groupedTransactions as $isin => $companyTransactions) {
            $summary = new TransactionSummary();
            $summary->isin = $isin;
            
            $names = [];
            foreach ($companyTransactions as $transactionType => $transactions) {
                $indexToSkip = null;
                $transactionTypeToSkip = null;

                foreach ($transactions as $index => $transaction) {
                    if ($indexToSkip === $index && $transactionTypeToSkip === $transaction->transactionType) {
                        echo $this->presenter->blueText("Skipping: {$transaction->name} ({$isin}) [{$transaction->date}]") . PHP_EOL;
                        continue;
                    }

                    $transactionAmount = $transaction->amount;

                    // echo $transaction->date . ': ' . $transaction->transactionType . ': avgift: ' . $transaction->fee . ' SEK' . PHP_EOL;

                    switch ($transactionType) {
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
                        case 'shareSplit':
                            $summary->currentNumberOfShares += round($transaction->quantity, 2);
                            break;
                        case 'shareTransfer':
                            if (!isset($transactions[$index + 1])) {
                                echo $this->presenter->blueText("Värdepappersflytt behandlas som såld för det finns inte några fler sådana transaktioner. {$transaction->name} ({$isin}) [{$transaction->date}]") . PHP_EOL;
                                $summary->sellAmountTotal += round($transaction->price * $transaction->quantity, 2);
                                $summary->currentNumberOfShares -= round($transaction->quantity, 2);
                                break;
                            }

                            $nextTransaction = $transactions[$index + 1];

                            // Avanza strategy
                            if ($transaction->bank === 'AVANZA' && $nextTransaction->bank === 'AVANZA') {
                                if ($transaction->isin !== $nextTransaction->isin) {
                                    break;
                                }

                                // Om den nästa transaktionen är av samma typ och samma (fast inverterade) quantity samt gjorda på samma datum så är det förmodligen en intern överföring inom samma bank. skippa dessa.
                                if ($transaction->transactionType === 'share_transfer' && $nextTransaction->transactionType === 'share_transfer') {
                                    if ($transaction->rawQuantity + $nextTransaction->rawQuantity == 0 && $transaction->date === $nextTransaction->date) {
                                        echo $this->presenter->blueText("Intern överföring inom samma bank: {$transaction->name} ({$isin}) [{$transaction->date}]") . PHP_EOL;
                                        $indexToSkip = $index + 1;
                                        $transactionTypeToSkip = 'share_transfer';
                                        break;
                                    } else {
                                        // behandla den som såld här?
                                        $summary->sellAmountTotal += round($transaction->price * $transaction->quantity, 2);
                                        $summary->currentNumberOfShares -= round($transaction->quantity, 2);
                                        break;
                                    }
                                }
                            }

                            break;
                        default:
                            echo $this->presenter->redText("Unknown transaction type: '{$transactionType}' in {$transaction->name} ({$isin}) [{$transaction->date}]") . PHP_EOL;
                            break;
                    }

                    if (!in_array($transaction->name, $names)) {
                        $names[] = $transaction->name;
                    }
                }
            }

            // TODO: Fulfix. Kolla upp orsaken.
            if (empty($names)) {
                print_r($summary);
                continue;
            }
            $summary->name = $names[0];
            $summary->isin = $isin;
            $summaries[] = $summary;
        }

        return $summaries;
    }

    private function lookForShareSplitsAvanza(Transaction $currentTransaction, Transaction $nextTransaction): ?int
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
