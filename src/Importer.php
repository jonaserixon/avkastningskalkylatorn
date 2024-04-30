<?php

class Importer
{
    public function parseBankTransactions()
    {
        // TODO: fixa stöd för nordnet osv.
        // TODO: gör den här mer dynamisk så att man slipper hårdkoda filnamnet
        $result = static::parseAvanzaTransactions(__DIR__ . '/../imports/test.csv');

        return $result;
    }

    /**
     * @return Transaction[]
     */
    private static function parseAvanzaTransactions(string $filename): array
    {
        $csvFile = file_get_contents($filename);
        $lines = explode(PHP_EOL, $csvFile);

        $result = [];
        foreach ($lines as $key => $line) {
            if ($key === 0) {
                continue;
            }

            $parsedCsv = str_getcsv($line, ';');
            if (empty($parsedCsv) || count($parsedCsv) < 9) {
                continue;
            }

            $transaction = new Transaction();
            $transaction->date = $parsedCsv[0];
            $transaction->account = $parsedCsv[1];
            $transaction->transactionType = $parsedCsv[2];
            $transaction->name = $parsedCsv[3];
            $transaction->quantity = abs((int) $parsedCsv[4]);
            $transaction->rawQuantity = (int) $parsedCsv[4];
            $transaction->price = abs(static::convertToFloat($parsedCsv[5]));
            $transaction->amount = abs(static::convertToFloat($parsedCsv[6]));
            $transaction->fee = static::convertToFloat($parsedCsv[7]);
            $transaction->currency = $parsedCsv[8];

            $result[] = $transaction;
        }

        return $result;
    }

    private static function convertToFloat(string $value): float
    {
        return floatval(str_replace(',', '.', str_replace('.', '', $value)));
    }
}
