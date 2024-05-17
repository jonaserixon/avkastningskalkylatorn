<?php

namespace src\Libs\FileManager;

abstract class CsvParser
{
    protected static string $DIR = '';

    abstract protected function parseTransactions(string $fileName): array;

    abstract protected function validateImportFile(string $filePath): bool;

    public function parseBankTransactions(): array
    {
        $result = [];

        if (!file_exists(static::$DIR)) {
            mkdir(static::$DIR, 0777, true);
        }

        // TODO: plocka alltid ut den senast modifierade filen här
        $files = glob(static::$DIR . '/*.csv');

        foreach ($files as $filepath) {
            $validatedBank = $this->validateImportFile($filepath);

            if ($validatedBank) {
                $result = static::parseTransactions($filepath);
                break;
            }
        }

        return $result;
    }

    protected function readCsvFile(string $fileName, string $separator): array
    {
        $file = fopen($fileName, 'r');

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
        $input = static::convertToUTF8($input);
        $input = mb_strtolower($input);
    
        return $input;
    }

    protected static function convertToUTF8(string $text): string
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
