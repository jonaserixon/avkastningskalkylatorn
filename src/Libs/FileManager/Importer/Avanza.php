<?php

namespace src\Libs\FileManager\Importer;

use Exception;
use src\DataStructure\Transaction;
use src\Enum\TransactionType;
use src\Libs\FileManager\CsvParser;

class Avanza extends CsvParser
{
    protected static string $DIR = IMPORT_DIR . '/banks/avanza';
    private const CSV_SEPARATOR = ";";
    private const BANK_NAME = 'AVANZA';

    protected function validateImportFile(string $filePath): bool
    {
        $handle = fopen($filePath, "r");
        if ($handle === false) {
            throw new Exception('Failed to open file: ' . basename($filePath));
        }

        $headers = fgetcsv($handle, 1000, static::CSV_SEPARATOR);
        if ($headers === false) {
            throw new Exception('Failed to read headers from file: ' . basename($filePath));
        }

        if (count($headers) !== 11) {
            throw new Exception('Invalid Avanza import file: ' . basename($filePath));
        }

        fclose($handle);

        return true;
    }

    /**
     * @return Transaction[]
     */
    protected function parseTransactions(string $fileName): array
    {
        $csvData = $this->readCsvFile($fileName, static::CSV_SEPARATOR);

        // Vi måste sortera på datum här så att vi enkelt kan hitta eventuella aktiesplittar.
        usort($csvData, function ($a, $b) {
            return strtotime($a[0]) <=> strtotime($b[0]);
        });

        $result = [];
        foreach ($csvData as $row) {
            $transactionType = static::mapToTransactionTypeByType($row[2]);

            if (!$transactionType) {
                continue;
            }

            // TODO: Kan rent teoretiskt sett urskilja på om det är en aktie eller fond baserat på om antalet är decimaltal eller inte. OBS: vanskligt

            $transaction = new Transaction();
            $transaction->bank = static::BANK_NAME;
            $transaction->date = $row[0]; // Datum
            $transaction->account = $row[1]; // Konto
            $transaction->name = trim($row[3]); // Värdepapper/beskrivning
            // $transaction->quantity = abs(static::convertToFloat($row[4])); // Antal
            $transaction->rawQuantity = static::convertToFloat($row[4]); // Antal
            // $transaction->price = abs(static::convertToFloat($row[5])); // Kurs
            $transaction->rawPrice = static::convertToFloat($row[5]); // Kurs
            // $transaction->amount = abs(static::convertToFloat($row[6])); // Belopp
            $transaction->rawAmount = static::convertToFloat($row[6]); // Belopp
            $transaction->commission = static::convertToFloat($row[7]); // Courtage
            $transaction->currency = $row[8]; // Valuta
            $transaction->isin = $row[9]; // ISIN
            // $transaction->isin = empty($row[9]) ? null : $row[9]; // ISIN

            if ($transactionType->value === 'other') {
                $transaction->type = $this->mapTransactionTypeByName($transaction);
            } else {
                $transaction->type = $transactionType->value; // Typ av transaktion
            }

            $result[] = $transaction;
        }

        return $result;
    }

    protected static function mapToTransactionTypeByType(?string $input): ?TransactionType
    {
        if (empty($input)) {
            return null;
        }

        $normalizedInput = static::normalizeInput($input);

        // Mappningstabell för att hantera olika termer från olika banker.
        $mapping = [
            'köpt' => 'buy',
            'sålt' => 'sell',
            'utdelning' => 'dividend',
            'köp' => 'buy',
            'sälj' => 'sell',
            'övrigt' => 'other',
            'värdepappersöverföring' => 'share_transfer',
            'insättning' => 'deposit',
            'uttag' => 'withdrawal',
            'ränta' => 'interest',

            'preliminärskatt' => 'tax',
            'utländsk källskatt' => 'foreign_withholding_tax',
        ];

        if (array_key_exists($normalizedInput, $mapping)) {
            return TransactionType::tryFrom($mapping[$normalizedInput]);
        }

        // echo bin2hex($normalizedInput) . PHP_EOL;
        // echo '-----------' . PHP_EOL;
        // print 'Unknown transaction type: ' . $normalizedInput . PHP_EOL;

        return null;
    }

    public function mapTransactionTypeByName(Transaction &$transaction)
    {
        $fees = ['avgift', 'riskpremie', 'adr'];

        foreach ($fees as $fee) {
            if (str_contains(mb_strtolower($transaction->name), $fee)) {
                return 'fee';
            }
        }

        // Återbetald utländsk källskatt
        if (str_contains(mb_strtolower($transaction->name), 'återbetalning') && str_contains(mb_strtolower($transaction->name), 'källskatt')) {
            return 'returned_foreign_withholding_tax';
        }

        $taxes = ['skatt']; // 'avkastningsskatt', 'källskatt',
        foreach ($taxes as $tax) {
            if (str_contains(mb_strtolower($transaction->name), $tax)) {
                return 'tax';
            }
        }

        // TODO: hantera aktiesplittar redan här?
        return 'other';
    }
}
