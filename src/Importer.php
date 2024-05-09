<?php

class Importer
{
    public function parseBankTransactions(): array
    {
        $result = [];

        $bankImports = [
            'avanza' => glob(__DIR__ . '/../imports/avanza/*.csv'),
            'nordnet' => glob(__DIR__ . '/../imports/nordnet/*.csv')
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

        throw new Exception('Unable to determine which bank in file: ' . basename($filePath));
    }

    /**
     * @return Transaction[]
     */
    private static function parseAvanzaTransactions(string $fileName, Bank $bank): array
    {
        $file = fopen($fileName, 'r');

        $result = [];
        while (($fields = fgetcsv($file, 0, ";")) !== false) {
            $transactionType = static::mapToTransactionType($fields[2]);
            if (!$transactionType) {
                continue;
            }

            $transaction = new Transaction();
            $transaction->bank = $bank->value;
            $transaction->date = $fields[0]; // Datum
            $transaction->account = $fields[1]; // Konto
            $transaction->transactionType = $transactionType->value; // Typ av transaktion
            $transaction->name = $fields[3]; // Värdepapper/beskrivning
            $transaction->quantity = abs((int) $fields[4]); // Antal
            $transaction->rawQuantity = (int) $fields[4]; // Antal
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

    private static function convertToUTF8(string $text): string
    {
        $encoding = mb_detect_encoding($text, mb_detect_order(), false);
        if ($encoding == "UTF-8") {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }
    
        $out = iconv(mb_detect_encoding($text, mb_detect_order(), false), "UTF-8//IGNORE", $text);
    
        return $out;
    }

    private static function convertToFloat(string $value): float
    {
        $value = str_replace(' ', '', $value);
        $value = str_replace(',', '.', str_replace('.', '', $value));

        return (float) $value;
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
            'övrigt' => 'other'
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
}
