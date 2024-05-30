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
        $csvData = $this->readCsvFile($fileName, static::CSV_SEPARATOR);

        $file = fopen($fileName, 'r');
        if ($file === false) {
            throw new Exception('Failed to open file: ' . basename($fileName));
        }
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

            $date = date_create($row[1]);
            if ($date === false) {
                echo "***** Could not parse date: {$row[1]}! *****" . PHP_EOL;
                continue;
            }

            $account = $row[4];
            $type = $transactionType->value;
            $name = trim($row[6]);
            $description = trim($row[23]);
            $rawQuantity = static::convertNumericToFloat($row[9]);
            $rawPrice = static::convertNumericToFloat($row[10]);
            $pricePerShareSEK = null;
            $rawAmount = static::convertNumericToFloat($row[14]);
            $commission = static::convertNumericToFloat($row[12]);
            $currency = $row[17];
            $isin = $row[8];

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
                isin: $isin
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

    public function mapTransactionTypeByName(TransactionType $type, string $description): TransactionType
    {
        // Återbetald utländsk källskatt
        if (str_contains(mb_strtolower($description), 'återbetalning') && str_contains(mb_strtolower($description), 'källskatt')) {
            return TransactionType::RETURNED_FOREIGN_WITHHOLDING_TAX;
        }

        return $type;
    }
}
