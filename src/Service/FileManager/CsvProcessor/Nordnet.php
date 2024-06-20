<?php

namespace src\Service\FileManager\CsvProcessor;

use Exception;
use src\DataStructure\Transaction;
use src\Enum\Bank;
use src\Enum\TransactionType;

class Nordnet extends CsvProcessor
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
        $csvData = $this->readCsvFileWithHeaders($fileName, static::CSV_SEPARATOR);

        $file = fopen($fileName, 'r');
        if ($file === false) {
            throw new Exception('Failed to open file: ' . basename($fileName));
        }
        fgetcsv($file); // Skip headers

        usort($csvData, function (array $a, array $b): int {
            return strtotime($a['Bokföringsdag']) <=> strtotime($b['Bokföringsdag']);
        });

        $result = [];
        foreach ($csvData as $row) {
            $transactionType = static::mapToTransactionType($row['Transaktionstyp'] ?? null);
            if (!$transactionType) {
                echo "***** Could not handle transaction: {$row['Transaktionstyp']} {$row['Värdepapper']} {$row['Bokföringsdag']}! *****" . PHP_EOL;
                continue;
            }

            $date = date_create($row['Bokföringsdag']);
            if ($date === false) {
                echo "***** Could not parse date: {$row['Bokföringsdag']}! *****" . PHP_EOL;
                continue;
            }

            $account = $row['Depå'];
            $type = $transactionType->value;
            $name = trim($row['Värdepapper']);
            $description = trim($row['Transaktionstext']);
            $rawQuantity = static::convertNumericToFloat($row['Antal']);
            $rawPrice = static::convertNumericToFloat($row['Kurs']);
            $pricePerShareSEK = null;
            $rawAmount = static::convertNumericToFloat($row['Belopp']);
            $commission = static::convertNumericToFloat($row['Total Avgift']);
            $currency = $row['Valuta_1'];
            $isin = $row['ISIN'];

            if (empty($currency)) {
                $currency = $row['Valuta_2'];
            }

            $exchangeRate = static::convertNumericToFloat($row['Växlingskurs']);

            if ($rawQuantity && $rawPrice && $rawAmount) {
                $pricePerShareSEK = abs($rawAmount) / abs($rawQuantity);
            }

            $type = $this->mapTransactionTypeByName($transactionType, $description);
            if ($type->value === 'sell') {
                $rawQuantity = -1 * $rawQuantity; // Sell transactions must have negative quantity
            }

            $transaction = new Transaction(
                date: $date,
                bank: Bank::NORDNET,
                account: $account,
                type: $type,
                name: $name,
                description: $description,
                rawQuantity: $rawQuantity,
                rawPrice: $rawPrice,
                pricePerShareSEK: $pricePerShareSEK,
                rawAmount: $rawAmount,
                commission: $commission,
                currency: $currency,
                isin: $isin,
                exchangeRate: $exchangeRate
            );

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

            'preliminärskatt' => 'tax', // TODO: verkar vara kopplat till sparkonto av någon anledning. kolla med nordnet.
            'avkastningsskatt' => 'tax',

            'utl källskatt' => 'foreign_withholding_tax',
            'mak utl källskatt' => 'foreign_withholding_tax', // makulerad källskatt

            'avgift' => 'fee', // ADR etc.
            'riskkostnad' => 'fee',
        ];

        if (array_key_exists($normalizedInput, $mapping)) {
            return TransactionType::tryFrom($mapping[$normalizedInput]);
        }

        return null;
    }

    public function mapTransactionTypeByName(TransactionType $type, string $description): TransactionType
    {
        // Återbetald utländsk källskatt
        if (str_contains(mb_strtolower($description), 'återbetalning') && str_contains(mb_strtolower($description), 'källskatt')) {
            return TransactionType::RETURNED_FOREIGN_WITHHOLDING_TAX;
        }

        return $type;
    }
}
