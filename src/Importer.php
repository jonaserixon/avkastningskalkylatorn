<?php

class Importer
{
    public function parseBankTransactions()
    {
        // TODO: gör den här mer dynamisk så att man slipper hårdkoda filnamnet
        // $result = static::parseAvanzaTransactions(__DIR__ . '/../imports/avanza.csv');
        $result = static::parseNordnetTransactions(__DIR__ . '/../imports/nordnet.csv');


        $files = glob(__DIR__ . '/../imports/*.csv');

        $result = [];
        foreach($files as $filepath) {
            $bank = $this->determineBankByHeaders($filepath);

            switch ($bank) {
                case 'AVANZA':
                    $result = array_merge($result, static::parseAvanzaTransactions($filepath));
                    break;
                case 'NORDNET':
                    $result = array_merge($result, static::parseNordnetTransactions($filepath));
                    break;
                case 'UNKNOWN_BANK':
                    throw new Exception('Unable to determine which bank in file: ' . basename($filepath));
            }
        }

        return $result;
    }

    private function determineBankByHeaders(string $filePath): string
    {
        if (($handle = fopen($filePath, "r")) !== false) {
            // Första kollen
            if (($headers = fgetcsv($handle, 1000, ";")) !== false) {
                if (count($headers) === 11) {
                    return 'AVANZA';
                }
            }
            
            if (($headers = fgetcsv($handle, 1000, "\t")) !== false) {
                if (count($headers) === 29) {
                    return 'NORDNET';
                }
            }

            fclose($handle);
        }
        
        return 'UNKNOWN_BANK';
    }

    /**
     * @return Transaction[]
     */
    private static function parseAvanzaTransactions(string $filename): array
    {
        $file = fopen($filename, 'r');

        $result = [];
        while (($fields = fgetcsv($file, 0, ";")) !== false) {
            $transactionType = static::mapToTransactionType($fields[2]);
            if (!$transactionType) {
                continue;
            }

            $transaction = new Transaction();
            $transaction->bank = 'avanza';
            $transaction->date = $fields[0];
            $transaction->account = $fields[1];
            $transaction->transactionType = $transactionType->value;
            $transaction->name = $fields[3];
            $transaction->quantity = abs((int) $fields[4]);
            $transaction->rawQuantity = (int) $fields[4];
            $transaction->price = abs(static::convertToFloat($fields[5]));
            $transaction->amount = abs(static::convertToFloat($fields[6]));
            $transaction->fee = static::convertToFloat($fields[7]);
            $transaction->currency = $fields[8];
            $transaction->isin = $fields[9];

            $result[] = $transaction;
        }
    
        fclose($file);

        return $result;
    }

    /**
     * @return Transaction[]
     */
    private static function parseNordnetTransactions(string $filename): array
    {
        $file = fopen($filename, 'r');

        $result = [];
        while (($fields = fgetcsv($file, 0, "\t")) !== false) {
            $transactionType = static::mapToTransactionType($fields[5] ?? null);
            if (!$transactionType) {
                continue;
            }

            $transaction = new Transaction();
            $transaction->bank = 'nordnet';
            $transaction->date = $fields[1]; // Affärsdag
            $transaction->account = $fields[4];
            $transaction->transactionType = $transactionType->value;
            $transaction->name = $fields[6];
            $transaction->quantity = abs((int) $fields[9]);
            $transaction->rawQuantity = (int) $fields[9];
            $transaction->price = abs(static::convertToFloat($fields[10]));
            $transaction->amount = abs(static::convertToFloat($fields[14])); // Belopp
            $transaction->fee = static::convertToFloat($fields[12]);
            $transaction->currency = $fields[17];
            $transaction->isin = $fields[8];

            $result[] = $transaction;
        }

        fclose($file);

        return $result;
    }

    private static function convertToFloat(string $value): float
    {
        $value = str_replace(' ', '', $value);
        $value = str_replace(',', '.', str_replace('.', '', $value));

        return (float) $value;
    }

    private static function mapToTransactionType($input): ?TransactionType
    {
        $normalizedInput = mb_strtolower($input);
    
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
    
        return null;
    }
}
