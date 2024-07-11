<?php declare(strict_types=1);

namespace Avk\Service\FileManager\CsvProcessor;

use Exception;
use Avk\DataStructure\Transaction;
use Avk\Enum\Bank;
use Avk\Enum\TransactionType;
use Avk\View\Logger;

class Custom extends CsvProcessor
{
    protected static string $DIR = IMPORT_DIR . '/banks/custom';
    protected const CSV_SEPARATOR = ';';

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
            throw new Exception('Invalid custom import file: ' . basename($filePath));
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

        usort($csvData, function (array $a, array $b): int {
            return strtotime($a['Date']) <=> strtotime($b['Date']);
        });

        $result = [];
        foreach ($csvData as $row) {
            $transactionType = static::mapToTransactionTypeByType($row['TransactionType']);
            if (!$transactionType) {
                Logger::getInstance()->addWarning("Could not handle transaction: {$row['Description']} {$row['Date']} when importing custom transactions.");
                continue;
            }

            $date = date_create($row['Date']);
            if ($date === false) {
                Logger::getInstance()->addWarning("Could not parse date: {$row['Date']} when importing custom transactions.");
                continue;
            }

            $account = $row['Account'];
            $name = trim($row['Description']);
            $rawQuantity = static::convertNumericToFloat($row['Quantity'], 5);
            $rawPrice = static::convertNumericToFloat($row['Price']);
            $pricePerShareSEK = null;
            $rawAmount = static::convertNumericToFloat($row['Amount'], 5);
            $commission = static::convertNumericToFloat($row['Fee']);
            $currency = $row['Currency'];
            $isin = $row['ISIN'];
            $type = $transactionType;
            $bank = Bank::tryFrom($row['Bank']) ?? Bank::NOT_SPECIFIED;

            // TODO: implement support for new isin codes (such as when share splits occur)
            $isin = $row['ISIN'];
            if ($isin === 'SE0000310336') {
                $isin = 'SE0015812219';
            }

            if ($rawQuantity && $rawPrice && $rawAmount) {
                $pricePerShareSEK = round(abs($rawAmount) / abs($rawQuantity), 3);
            }

            $transaction = new Transaction(
                date: $date,
                bank: $bank,
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
                isin: $isin,
                exchangeRate: null
            );

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

        return TransactionType::tryFrom($normalizedInput);
    }
}
