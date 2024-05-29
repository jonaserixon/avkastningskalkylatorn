<?php

namespace src\Service\FileManager\CsvProcessor;

use Exception;
use src\DataStructure\Transaction;

abstract class CsvProcessor
{
    protected static string $DIR = '';

    /** @return Transaction[] */
    abstract protected function parseTransactions(string $fileName): array;

    abstract protected function validateImportFile(string $filePath): bool;

    /**
     * @return Transaction[]
     */
    public function parseBankTransactions(): array
    {
        $result = [];

        if (!file_exists(static::$DIR)) {
            mkdir(static::$DIR, 0777, true);
        }

        // TODO: plocka alltid ut den senast modifierade filen här
        $files = glob(static::$DIR . '/*.csv');
        if (empty($files)) {
            return $result;
        }

        foreach ($files as $filepath) {
            $validatedBank = $this->validateImportFile($filepath);

            if ($validatedBank) {
                $result = static::parseTransactions($filepath);
                break;
            }
        }

        return $result;
    }

    /**
     * @return mixed[]
     */
    protected function readCsvFile(string $fileName, string $separator): array
    {
        $this->convertToUTF8($fileName);

        $file = fopen($fileName, 'r');
        if ($file === false) {
            throw new Exception('Failed to open file: ' . basename($fileName));
        }
        fgetcsv($file); // Skip headers

        $result = [];
        while (($fields = fgetcsv($file, 0, $separator)) !== false) {
            $result[] = $fields;
        }

        fclose($file);

        return $result;
    }

    protected static function normalizeInput(string $input): string
    {
        $input = trim($input);
        $input = mb_strtolower($input);

        return $input;
    }

    protected static function convertToUTF8(string $fileName): void
    {
        $fileContent = file_get_contents($fileName);
        if ($fileContent === false) {
            throw new Exception('Failed to read file: ' . basename($fileName));
        }
        $currentEncoding = mb_detect_encoding($fileContent, mb_list_encodings(), true);
        if ($currentEncoding === false) {
            throw new Exception('Failed to detect encoding of file: ' . basename($fileName));
        }

        if ($currentEncoding === 'UTF-8') {
            return;
        }

        $utf8Content = mb_convert_encoding($fileContent, 'UTF-8', $currentEncoding);
        $utf8ContentWithBom = "\xEF\xBB\xBF" . $utf8Content;
        file_put_contents($fileName, $utf8ContentWithBom);
    }

    public static function convertNumericToFloat(string $value, int $numberOfDecimals = 2): ?float
    {
        // if (empty($value)) {
        //     return null;
        // }

        $value = str_replace(' ', '', $value);
        $value = str_replace(',', '.', str_replace('.', '', $value));

        $value = (float) $value;

        return round($value, $numberOfDecimals);
    }
}