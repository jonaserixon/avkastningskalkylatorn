<?php

namespace src\Libs\FileManager\Importer;

use Exception;
use src\DataStructure\Transaction;
use src\Enum\TransactionType;

class Nordnet extends CsvParser
{
    protected static string $DIR = IMPORT_DIR . '/banks/nordnet';
    protected const CSV_SEPARATOR = "\t";
    protected const BANK_NAME = 'NORDNET';

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

        if (count($headers) !== 29) {
            throw new Exception('Invalid Nordnet import file: ' . basename($filePath));
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

        $file = fopen($fileName, 'r');
        fgetcsv($file); // Skip headers

        usort($csvData, function ($a, $b) {
            return strtotime($a[1]) <=> strtotime($b[1]);
        });

        $result = [];
        foreach ($csvData as $row) {
            $transactionType = static::mapToTransactionType($row[5] ?? null);
            if (!$transactionType) {
                echo "***** Could not handle transaction: {$row[5]} {$row[6]} {$row[1]}! *****" . PHP_EOL;
                continue;
            }

            $transaction = new Transaction();
            $transaction->bank = static::BANK_NAME;
            $transaction->date = $row[1]; // Affärsdag
            $transaction->account = $row[4]; // Depå
            $transaction->type = $transactionType->value; // Transaktionstyp
            $transaction->name = trim($row[6]); // Värdepapper
            $transaction->rawQuantity = (int) $row[9]; // Antal
            $transaction->rawPrice = static::convertNumericToFloat($row[10]); // Kurs
            $transaction->rawAmount = static::convertNumericToFloat($row[14]); // Belopp
            $transaction->commission = static::convertNumericToFloat($row[12]); // Total Avgift
            $transaction->currency = $row[17]; // Valuta
            $transaction->isin = $row[8]; // ISIN
            $transaction->description = trim($row[23]); // Transaktionstext

            if ($transaction->rawQuantity && $transaction->rawPrice) {
                $transaction->pricePerShareSEK = abs($transaction->rawAmount) / abs($transaction->rawQuantity);
            }

            $transaction->type = $this->mapTransactionTypeByName($transaction);

            if ($transaction->type === 'sell') {
                $transaction->rawQuantity = -1 * $transaction->rawQuantity;
            }

            $result[] = $transaction;
        }

        return $result;
    }

    protected static function mapToTransactionType(?string $input): ?TransactionType
    {
        if (empty($input)) {
            return null;
        }

        $normalizedInput = static::normalizeInput($input);

        $mapping = [
            'köpt' => 'buy',
            'sålt' => 'sell',
            'utdelning' => 'dividend',
            'mak utdelning' => 'dividend', // makulerad utdelning
            'insättning' => 'deposit',
            'premieinbetalning' => 'deposit', // insättning till KF
            'uttag' => 'withdrawal',

            'ränta' => 'interest',
            'kap överbel.ränta' => 'interest',
            'kap. deb ränta' => 'interest',
            'kap ränta' => 'interest',

            'källskatt' => 'tax', // TODO: verkar vara kopplat till sparkonto av någon anledning. kolla med nordnet.
            'avkastningsskatt' => 'tax',

            'utl kupskatt' => 'foreign_withholding_tax',
            'mak utl kupskatt' => 'foreign_withholding_tax', // makulerad källskatt

            'avgift' => 'fee', // ADR etc.
            'riskkostnad' => 'fee',
        ];

        if (array_key_exists($normalizedInput, $mapping)) {
            return TransactionType::tryFrom($mapping[$normalizedInput]);
        }

        return null;
    }

    public function mapTransactionTypeByName(Transaction &$transaction): string
    {
        // Återbetald utländsk källskatt
        if (str_contains(mb_strtolower($transaction->description), 'återbetalning') && str_contains(mb_strtolower($transaction->description), 'källskatt')) {
            return 'returned_foreign_withholding_tax';
        }

        return $transaction->type;
    }
}
