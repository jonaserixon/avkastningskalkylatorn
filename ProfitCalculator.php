<?php

require_once 'DataStructure/Transaction.php';
require_once 'DataStructure/TransactionSummary.php';

$transactions = parseAvanzaExport('Imports/test.csv'); // TODO: gör den här mer dynamisk så att man slipper hårdkoda filnamnet
$groupedTransactions = groupTransactions($transactions);
$summaries = summarizeTransactions($groupedTransactions);

$currentSharePrices = [
    'Evolution' => 1224.50,
    'Fast. Balder B' => 69.46,
    'British American Tobacco ADR' => 322.7629,
    'Philip Morris' => 1044.908
];

presentResult($summaries, $currentSharePrices);

/**
 * Avanza verkar inte skicka med rätt valuta i exporten. Därför behöver vi gissa oss fram.
 */
function guessCurrencyBasedOnISIN(Transaction $transaction)
{

}

function groupTransactions(array $transactions): array
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
            print_r(PHP_EOL . '!!OBS!! ' . $transaction->companyName . ' innehåller en eller flera aktiesplittar. Dubbelkolla alltid.' . PHP_EOL);
            continue;
        }

        if (in_array(strtolower($transaction->companyName), $blackListedTransactionNames)) {
            continue;
        }
        foreach ($blackListedTransactionNames as $blackListedTransactionName) {
            if (str_contains(strtolower($transaction->companyName), $blackListedTransactionName)) {
                print_r($transaction->companyName);
                continue 2;
            }
        }

        if (!array_key_exists($transaction->companyName, $groupedTransactions)) {
            $groupedTransactions[$transaction->companyName] = [
                'buy' => [],
                'sell' => [],
                'dividend' => [],
                'shareSplit' => []
            ];
        }
        switch ($transaction->transactionType) {
            case 'Köp':
                $groupedTransactions[$transaction->companyName]['buy'][] = $transaction;
                break;
            case 'Sälj':
                $groupedTransactions[$transaction->companyName]['sell'][] = $transaction;
                break;
            case 'Utdelning':
                $groupedTransactions[$transaction->companyName]['dividend'][] = $transaction;
                break;
            case 'Övrigt':
                $nextTransaction = $transactions[$index + 1];
                $shareSplitQuantity = lookForShareSplits($transaction, $nextTransaction);

                if ($shareSplitQuantity) {
                    $transaction->quantity = $shareSplitQuantity;
                    $groupedTransactions[$transaction->companyName]['shareSplit'][] = $transaction;
                    // $groupedTransactions[$transaction->companyName]['shareSplit'][] = $nextTransaction;
                    $indexToSkip = $index + 1;
                }
                break;
        }
    }

    return $groupedTransactions;
}

function lookForShareSplits($currentTransaction, $nextTransaction)
{
    if ($currentTransaction->companyName !== $nextTransaction->companyName) {
        return false;
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
}

function summarizeTransactions(array $groupedTransactions): array
{
    $summaries = [];
    foreach ($groupedTransactions as $companyName => $companyTransactions) {
        $summary = new TransactionSummary();

        foreach ($companyTransactions as $transactionType => $transactions) {
            foreach ($transactions as $transaction) {
                // $transactionAmount = $transaction->price * $transaction->quantity; // Det funkar inte om avanza inte skickar med valutan i exporten
                $transactionAmount = $transaction->amount;

                

                // TODO: Inkludera andra typer av avgifter och skatter. T.ex. ADR och källskatt(?)

                if ($transactionType === 'buy') {
                    $summary->buyAmountTotal += $transaction->amount;
                    $summary->currentNumberOfShares += $transaction->quantity;
                    $summary->feeAmountTotal += $transaction->fee;
                } elseif ($transactionType === 'sell') {
                    $summary->sellAmountTotal += $transaction->amount;
                    $summary->currentNumberOfShares -= $transaction->quantity;
                    $summary->feeAmountTotal += $transaction->fee;
                } elseif ($transactionType === 'dividend') {
                    // $summary->dividendAmountTotal += $price * $quantity;

                    $summary->dividendAmountTotal += $transaction->amount;
                } elseif ($transactionType === 'shareSplit') {
                    $summary->currentNumberOfShares += $transaction->quantity;
                } else {
                    throw new Exception('Unknown transaction type: ' . $transactionType);
                }
            }
        }

        $summary->companyName = $companyName;
        $summaries[] = $summary;
    }

    return $summaries;
}

function presentResult(array $summaries, array $currentSharePrices): void
{
    echo '**********************************' . PHP_EOL;
    foreach ($summaries as $summary) {
        $currentPricePerShare = $currentSharePrices[$summary->companyName] ?? null;
        displayFormattedSummary($summary, $currentPricePerShare);
    }
    echo PHP_EOL . '**********************************' . PHP_EOL;
}

function displayFormattedSummary(TransactionSummary $summary, ?float $currentPricePerShare): void
{
    $currentValueOfShares = null;
    if ($currentPricePerShare) {
        $currentValueOfShares = $summary->currentNumberOfShares * $currentPricePerShare;
    }

    $totalProfit = ($summary->sellAmountTotal + $summary->dividendAmountTotal + $currentValueOfShares) - ($summary->buyAmountTotal + $summary->feeAmountTotal);

    echo "\n------ ". $summary->companyName ." ------\n";
    echo "Köpbelopp: " . number_format($summary->buyAmountTotal, 2) . " SEK\n";
    echo "Säljbelopp: " . number_format($summary->sellAmountTotal, 2) . " SEK\n";
    echo "Utdelningar: " . number_format($summary->dividendAmountTotal, 2) . " SEK\n";
    echo "Avgifter: " . number_format($summary->feeAmountTotal, 2) . " SEK\n";

    if ($currentValueOfShares) {
        echo "Nuvarande antal aktier: " . $summary->currentNumberOfShares . " st\n";
        echo "Nuvarande pris per aktie: " . number_format($currentPricePerShare, 2) . " SEK\n";
        echo "Nuvarande marknadsvärde för aktier: " . number_format($currentValueOfShares, 2) . " SEK\n";
    }

    echo "Total vinst/förlust: " . number_format($totalProfit, 2) . " SEK\n";
    echo "----------------------------------------\n";
}

function convertToFloat(string $value): float
{
    return floatval(str_replace(',', '.', str_replace('.', '', $value)));
}

function parseAvanzaExport($filename)
{
    $csvFile = file_get_contents($filename);
    $lines = explode(PHP_EOL, $csvFile);
    
    $result = [];
    foreach ($lines as $key => $line) {
        if ($key === 0) {
            continue;
        }

        $parsedCsv = str_getcsv($line, ';');
        if (empty($parsedCsv) || count($parsedCsv) < 9) {
            continue;
        }
        
        $transaction = new Transaction();
        $transaction->date = $parsedCsv[0];
        $transaction->account = $parsedCsv[1];
        $transaction->transactionType = $parsedCsv[2];
        $transaction->companyName = $parsedCsv[3];
        $transaction->quantity = abs((int) $parsedCsv[4]);
        $transaction->rawQuantity = (int) $parsedCsv[4];
        $transaction->price = abs(convertToFloat($parsedCsv[5]));
        $transaction->amount = abs(convertToFloat($parsedCsv[6]));
        $transaction->fee = convertToFloat($parsedCsv[7]);
        $transaction->currency = $parsedCsv[8];

        $result[] = $transaction;
    }

    return $result;
}
