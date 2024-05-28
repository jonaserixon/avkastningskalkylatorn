<?php

namespace src\Libs\FileManager\Importer;

use Exception;
use src\DataStructure\Transaction;
use src\Enum\TransactionType;

class Avanza extends CsvParser
{
    protected static string $DIR = IMPORT_DIR . '/banks/avanza';
    protected const CSV_SEPARATOR = ";";
    protected const BANK_NAME = 'AVANZA';

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

        usort($csvData, function ($a, $b) {
            return strtotime($a[0]) <=> strtotime($b[0]);
        });

        $result = [];
        foreach ($csvData as $row) {
            $transactionType = static::mapToTransactionTypeByType($row[2]);
            if (!$transactionType) {
                echo "***** Could not handle transaction: {$row[3]} {$row[0]}! *****" . PHP_EOL;
                continue;
            }

            $transaction = new Transaction();
            $transaction->bank = static::BANK_NAME;
            $transaction->date = $row[0]; // Datum
            $transaction->account = $row[1]; // Konto
            $transaction->name = trim($row[3]); // Värdepapper/beskrivning
            $transaction->rawQuantity = static::convertNumericToFloat($row[4], 5); // Antal
            $transaction->rawPrice = static::convertNumericToFloat($row[5]); // Kurs
            $transaction->rawAmount = static::convertNumericToFloat($row[6], 5); // Belopp
            $transaction->commission = static::convertNumericToFloat($row[7]); // Courtage
            $transaction->currency = $row[8]; // Valuta

            // TODO: implement support for new isin codes (such as when share splits occur)
            $isin = $row[9];
            if ($isin === 'SE0000310336') {
                $transaction->isin = 'SE0015812219';
            } else {
                $transaction->isin = $isin;
            }

            if ($transaction->rawQuantity && $transaction->rawPrice) {
                $transaction->pricePerShareSEK = round(abs($transaction->rawAmount) / abs($transaction->rawQuantity), 3);
            }

            if ($transactionType->value === 'other') {
                $transaction->type = $this->mapTransactionTypeByName($transaction);
            } else {
                $transaction->type = $transactionType->value; // Typ av transaktion
            }

            $this->bankSpecificHandling($transaction);

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
            'utbetalning aktielån' => 'share_loan_payout',
        ];

        if (array_key_exists($normalizedInput, $mapping)) {
            return TransactionType::tryFrom($mapping[$normalizedInput]);
        }

        return null;
    }

    protected function bankSpecificHandling(Transaction &$transaction): void
    {
        if ($transaction->type === 'other') {
            // TODO: add a specific type for this so the actual deposits can be kept clean.
            if (str_contains(mb_strtolower($transaction->name), 'kapitalmedelskonto') || str_contains(mb_strtolower($transaction->name), 'nollställning')) {
                $transaction->type = 'deposit';
                return;
            }
        }

        // Check if this can be considered a share split.
        if ($transaction->type === 'other' && $transaction->rawQuantity != 0 && $transaction->commission == 0) {
            $transaction->type = 'share_split';
            return;
        }

        // if ($transaction->type === 'deposit') {
        //     if (str_contains(mb_strtolower($transaction->name), 'kreditdepån')) {
        //         $transaction->type = 'deposit';
        //         return;
        //     }
        // }

        // if ($transaction->type === 'withdrawal') {
        //     if (str_contains(mb_strtolower($transaction->name), 'kapitalmedelskonto') || str_contains(mb_strtolower($transaction->name), 'nollställning')) {
        //         $transaction->type = 'withdrawal';
        //         return;
        //     }
        // }
    }

    public function mapTransactionTypeByName(Transaction &$transaction): string
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
