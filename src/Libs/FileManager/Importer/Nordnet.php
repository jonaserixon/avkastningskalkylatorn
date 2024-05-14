<?php

namespace src\Libs\FileManager\Importer;

use Exception;
use src\DataStructure\Transaction;
use src\Enum\TransactionType;
use src\Libs\FileManager\CsvParser;

class Nordnet extends CsvParser
{
    protected const DIR = IMPORT_DIR . '/banks/nordnet';

    protected function validateImportFile(string $filePath): bool
    {
        if (($handle = fopen($filePath, "r")) !== false) {
            // tab separerad
            if (($headers = fgetcsv($handle, 1000, "\t")) !== false) {
                if (count($headers) === 29) {
                    return true;
                }
            }

            fclose($handle);
        }

        throw new Exception('Invalid Nordnet import file: ' . basename($filePath));
    }

    /**
     * @return Transaction[]
     */
    protected function parseTransactions(string $fileName): array
    {
        $file = fopen($fileName, 'r');

        // TODO: sortera på datum

        $result = [];
        while (($fields = fgetcsv($file, 0, "\t")) !== false) {
            $transactionType = static::mapToTransactionType($fields[5] ?? null);
            if (!$transactionType) {
                continue;
            }

            $transaction = new Transaction();
            $transaction->bank = 'NORDNET';
            $transaction->date = $fields[1]; // Affärsdag
            $transaction->account = $fields[4]; // Depå
            $transaction->type = $transactionType->value; // Transaktionstyp
            $transaction->name = trim($fields[6]); // Värdepapper
            $transaction->quantity = abs((int) $fields[9]); // Antal
            $transaction->rawQuantity = (int) $fields[9]; // Antal
            $transaction->price = abs(static::convertToFloat($fields[10])); // Kurs
            $transaction->amount = abs(static::convertToFloat($fields[14])); // Belopp
            $transaction->fee = static::convertToFloat($fields[12]); // Total Avgift
            $transaction->currency = $fields[17]; // Valuta
            $transaction->isin = $fields[8]; // ISIN

            $result[] = $transaction;
        }

        fclose($file);

        return $result;
    }

    protected static function mapToTransactionType(?string $input): ?TransactionType
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
        ];

        if (array_key_exists($normalizedInput, $mapping)) {
            return TransactionType::tryFrom($mapping[$normalizedInput]);
        }

        // echo bin2hex($normalizedInput) . PHP_EOL;
        // echo '-----------' . PHP_EOL;
        // print 'Unknown transaction type: ' . $normalizedInput . PHP_EOL;

        return null;
    }
}
