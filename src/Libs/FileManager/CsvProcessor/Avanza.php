<?php

namespace src\Libs\FileManager\CsvProcessor;

use Exception;
use src\DataStructure\Transaction;
use src\Enum\TransactionType;
use src\Libs\Utility;

class Avanza extends CsvProcessor
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

            $date = date_create($row[0]);
            if ($date === false) {
                echo "***** Could not parse date: {$row[0]}! *****" . PHP_EOL;
                continue;
            }

            $account = $row[1];
            $name = trim($row[3]);
            $rawQuantity = static::convertNumericToFloat($row[4], 5);
            $rawPrice = static::convertNumericToFloat($row[5]);
            $pricePerShareSEK = null;
            $rawAmount = static::convertNumericToFloat($row[6], 5);
            $commission = static::convertNumericToFloat($row[7]);
            $currency = $row[8];
            $isin = $row[9];
            $type = $transactionType->value;

            // TODO: implement support for new isin codes (such as when share splits occur)
            $isin = $row[9];
            if ($isin === 'SE0000310336') {
                $isin = 'SE0015812219';
            }

            if ($rawQuantity && $rawPrice && $rawAmount) {
                $pricePerShareSEK = round(abs($rawAmount) / abs($rawQuantity), 3);
            }

            // TODO: improve this logic
            if ($type === 'other') { // Special handling of "övrigt" since it contains so many different types of transactions.
                $type = $this->mapOtherTransactionType($name);
            }
            $type = $this->customTransactionTypeMapper($name, $type, $rawQuantity, $commission, $rawAmount);

            if ($type === 'share_split') {
                // $rawQuantity = null;
                // $rawPrice = null;
                // $pricePerShareSEK = null;
            }

            $transaction = new Transaction(
                date: $date,
                bank: static::BANK_NAME,
                account: $account,
                type: $type,
                name: $name,
                description: null,
                rawQuantity: $rawQuantity,
                rawPrice: $rawPrice,
                pricePerShareSEK: $pricePerShareSEK,
                rawAmount: $rawAmount,
                commission: $commission,
                currency: $currency,
                isin: $isin
            );

            $result[] = $transaction;
        }

        // exit;

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

    protected function customTransactionTypeMapper(string $name, string $type, ?float $rawQuantity, ?float $commission, ?float $rawAmount): string
    {
        if ($type === 'other') {
            // TODO: add a specific type for this so the actual deposits can be kept clean.
            if (Utility::strContains($name, 'kapitalmedelskonto') ||
                Utility::strContains($name, 'nollställning')
            ) {
                return 'deposit';
            }
        }

        // Check if this can be considered a share split.
        if ($type === 'other' && $rawQuantity != 0 && $commission == 0 && empty($rawAmount)) {
            return 'share_split';
        }

        /*
        if ($transaction->type === 'deposit') {
            if (str_contains(mb_strtolower($transaction->name), 'kreditdepån')) {
                $transaction->type = 'deposit';
                return;
            }
        }

        if ($transaction->type === 'withdrawal') {
            if (str_contains(mb_strtolower($transaction->name), 'kapitalmedelskonto') || str_contains(mb_strtolower($transaction->name), 'nollställning')) {
                $transaction->type = 'withdrawal';
                return;
            }
        }
        */

        return $type;
    }

    public function mapOtherTransactionType(string $name): string
    {
        $fees = ['avgift', 'riskpremie', 'adr'];
        foreach ($fees as $fee) {
            if (Utility::strContains($name, $fee)) {
                return 'fee';
            }
        }

        // Återbetald utländsk källskatt
        if (Utility::strContains($name, 'återbetalning') &&
            Utility::strContains($name, 'källskatt')
        ) {
            return 'returned_foreign_withholding_tax';
        }

        $taxes = ['skatt']; // 'avkastningsskatt', 'källskatt',
        foreach ($taxes as $tax) {
            if (Utility::strContains($name, $tax)) {
                return 'tax';
            }
        }

        return 'other';
    }
}
