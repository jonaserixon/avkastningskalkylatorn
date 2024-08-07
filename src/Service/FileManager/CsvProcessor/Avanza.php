<?php

declare(strict_types=1);

namespace Avk\Service\FileManager\CsvProcessor;

use Exception;
use Avk\DataStructure\Transaction;
use Avk\Enum\Bank;
use Avk\Enum\TransactionType;
use Avk\Service\Utility;
use Avk\View\Logger;

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
            return strtotime($a['Datum']) <=> strtotime($b['Datum']);
        });

        $result = [];
        foreach ($csvData as $row) {
            $transactionType = static::mapToTransactionTypeByType($row['Typ av transaktion']);
            if (!$transactionType) {
                Logger::getInstance()->addWarning("Could not handle transaction: {$row['Värdepapper/beskrivning']} {$row['Datum']} when importing Avanza transactions.");
                continue;
            }

            $date = date_create($row['Datum']);
            if ($date === false) {
                Logger::getInstance()->addWarning("Could not parse date: {$row['Datum']} when importing Avanza transactions.");
                continue;
            }

            $account = $row['Konto'];
            $name = trim($row['Värdepapper/beskrivning']);
            $rawQuantity = static::convertNumericToFloat($row['Antal'], 5);
            $rawPrice = static::convertNumericToFloat($row['Kurs']);
            $pricePerShareSEK = null;
            $rawAmount = static::convertNumericToFloat($row['Belopp'], 5);
            $commission = static::convertNumericToFloat($row['Courtage']);
            $currency = $row['Valuta'];
            $isin = $row['ISIN'];
            $type = $transactionType;

            // TODO: implement support for new isin codes (such as when share splits occur)
            $isin = $row['ISIN'];
            if ($isin === 'SE0000310336') {
                $isin = 'SE0015812219';
            }

            if ($rawQuantity && $rawPrice && $rawAmount) {
                $pricePerShareSEK = round(abs($rawAmount) / abs($rawQuantity), 3);
            }

            // TODO: improve this logic
            if ($type === TransactionType::OTHER) { // Special handling of "övrigt" since it contains so many different types of transactions.
                $type = $this->mapOtherTransactionType($name);
            }
            $type = $this->customTransactionTypeMapper($name, $type, $rawQuantity, $commission, $rawAmount);

            $transaction = new Transaction(
                date: $date,
                bank: Bank::AVANZA,
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

    protected function customTransactionTypeMapper(string $name, TransactionType $type, ?float $rawQuantity, ?float $commission, ?float $rawAmount): TransactionType
    {
        if ($type === TransactionType::OTHER) {
            // TODO: add a specific type for this so the actual deposits can be kept clean.
            if (Utility::strContains($name, 'kapitalmedelskonto') ||
                Utility::strContains($name, 'nollställning')
            ) {
                return TransactionType::DEPOSIT;
            }
        }

        // Check if this can be considered a share split.
        if ($type === TransactionType::OTHER && !empty($rawQuantity) && empty($commission) && empty($rawAmount)) {
            return TransactionType::SHARE_SPLIT;
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

    public function mapOtherTransactionType(string $name): TransactionType
    {
        $fees = ['avgift', 'riskpremie', 'adr'];
        foreach ($fees as $fee) {
            if (Utility::strContains($name, $fee)) {
                return TransactionType::FEE;
            }
        }

        // Återbetald utländsk källskatt
        if (Utility::strContains($name, 'återbetalning') &&
            Utility::strContains($name, 'källskatt')
        ) {
            return TransactionType::RETURNED_FOREIGN_WITHHOLDING_TAX;
        }

        $taxes = ['skatt']; // 'avkastningsskatt', 'källskatt',
        foreach ($taxes as $tax) {
            if (Utility::strContains($name, $tax)) {
                return TransactionType::TAX;
            }
        }

        return TransactionType::OTHER;
    }
}
