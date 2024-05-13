<?php

namespace src\Libs\FileManager;

use src\DataStructure\Transaction;
use src\Enum\Bank;
use src\Enum\TransactionType;
use Exception;

class Importer
{
    public function parseBankTransactions(): array
    {
        $result = [];

        if (!file_exists(IMPORT_DIR . '/banks/avanza')) {
            mkdir(IMPORT_DIR . '/banks/avanza', 0777, true);
        }
        if (!file_exists(IMPORT_DIR . '/banks/nordnet')) {
            mkdir(IMPORT_DIR . '/banks/nordnet', 0777, true);
        }

        $bankImports = [
            'avanza' => glob(IMPORT_DIR . '/banks/avanza/*.csv'),
            'nordnet' => glob(IMPORT_DIR . '/banks/nordnet/*.csv')
        ];

        foreach($bankImports as $bank => $files) {
            foreach ($files as $filepath) {
                $validatedBank = $this->determineBankByHeaders($filepath, $bank);

                switch ($validatedBank) {
                    case Bank::AVANZA:
                        $result = array_merge($result, static::parseAvanzaTransactions($filepath, $validatedBank));
                        break;
                    case Bank::NORDNET:
                        $result = array_merge($result, static::parseNordnetTransactions($filepath, $validatedBank));
                        break;
                }
            }
        }

        return $result;
    }

    private function determineBankByHeaders(string $filePath, string $bankDirectory): Bank
    {
        // TODO: Förbättra hur man avgör vilken bank det är.
        if (($handle = fopen($filePath, "r")) !== false) {
            // semi-colon separerad
            if ($bankDirectory === 'avanza' && ($headers = fgetcsv($handle, 1000, ";")) !== false) {
                if (count($headers) === 11) {
                    return Bank::AVANZA;
                }
            }

            // tab separerad
            if ($bankDirectory === 'nordnet' && ($headers = fgetcsv($handle, 1000, "\t")) !== false) {
                if (count($headers) === 29) {
                    return Bank::NORDNET;
                }
            }

            fclose($handle);
        }

        throw new Exception('Unable to determine the bank in file: ' . basename($filePath));
    }

    /**
     * @return Transaction[]
     */
    private static function parseAvanzaTransactions(string $fileName, Bank $bank): array
    {
        $file = fopen($fileName, 'r');

        $sortedFields = [];
        while (($fields = fgetcsv($file, 0, ";")) !== false) {
            $sortedFields[] = $fields;
        }

        // Vi måste sortera på datum här så att vi enkelt kan hitta eventuella aktiesplittar.
        usort($sortedFields, function($a, $b) {
            return strtotime($a[0]) <=> strtotime($b[0]);
        });

        $result = [];
        foreach ($sortedFields as $fields) {
            $transactionType = static::mapToTransactionType($fields[2]);

            if (!$transactionType) {
                continue;
            }

            // TODO: Kan rent teoretiskt sett urskilja på om det är en aktie eller fond baserat på om antalet är decimaltal eller inte.

            $transaction = new Transaction();
            $transaction->bank = $bank->value;
            $transaction->date = $fields[0]; // Datum
            $transaction->account = $fields[1]; // Konto
            $transaction->transactionType = $transactionType->value; // Typ av transaktion
            $transaction->name = $fields[3]; // Värdepapper/beskrivning
            $transaction->quantity = abs(static::convertToFloat($fields[4])); // Antal
            $transaction->rawQuantity = static::convertToFloat($fields[4]); // Antal
            $transaction->price = abs(static::convertToFloat($fields[5])); // Kurs
            $transaction->amount = abs(static::convertToFloat($fields[6])); // Belopp
            $transaction->fee = static::convertToFloat($fields[7]); // Courtage
            $transaction->currency = $fields[8]; // Valuta
            $transaction->isin = $fields[9]; // ISIN

            $result[] = $transaction;
        }

        fclose($file);

        return $result;
    }

    /**
     * @return Transaction[]
     */
    private static function parseNordnetTransactions(string $fileName, Bank $bank): array
    {
        $file = fopen($fileName, 'r');

        $result = [];
        while (($fields = fgetcsv($file, 0, "\t")) !== false) {
            $transactionType = static::mapToTransactionType($fields[5] ?? null);
            if (!$transactionType) {
                continue;
            }

            $transaction = new Transaction();
            $transaction->bank = $bank->value;
            $transaction->date = $fields[1]; // Affärsdag
            $transaction->account = $fields[4]; // Depå
            $transaction->transactionType = $transactionType->value; // Transaktionstyp
            $transaction->name = $fields[6]; // Värdepapper
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

    private static function mapToTransactionType(?string $input): ?TransactionType
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

    private static function normalizeInput(string $input): string
    {
        $input = trim($input);
        $input = static::convertToUTF8($input);
        $input = mb_strtolower($input);
    
        return $input;
    }

    private static function convertToUTF8(string $text): string
    {
        $encoding = mb_detect_encoding($text, mb_detect_order(), false);
        if ($encoding == "UTF-8") {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }
    
        $out = iconv(mb_detect_encoding($text, mb_detect_order(), false), "UTF-8//IGNORE", $text);
    
        return $out;
    }

    public static function convertToFloat(string $value): float
    {
        $value = str_replace(' ', '', $value);
        $value = str_replace(',', '.', str_replace('.', '', $value));

        return (float) $value;
    }
}
