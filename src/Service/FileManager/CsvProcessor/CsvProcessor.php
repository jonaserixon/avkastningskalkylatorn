<?php declare(strict_types=1);

namespace Avk\Service\FileManager\CsvProcessor;

use Exception;
use Avk\DataStructure\Transaction;
use Avk\Service\Utility;

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

        $transactionFile = Utility::getLatestModifiedFile(static::$DIR, 'csv');
        if ($transactionFile === null) {
            return $result;
        }

        $validatedBank = $this->validateImportFile($transactionFile);

        if ($validatedBank) {
            $result = static::parseTransactions($transactionFile);
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

    /**
     * @return mixed[]
     */
    protected function readCsvFileWithHeaders(string $fileName, string $separator): array
    {
        $this->convertToUTF8($fileName);

        $file = fopen($fileName, 'r');
        if ($file === false) {
            throw new Exception('Failed to open file: ' . basename($fileName));
        }
    
        $rawHeaders = fgetcsv($file, 0, $separator);
        if ($rawHeaders === false) {
            throw new Exception('Failed to read headers from file: ' . basename($fileName));
        }
        $headers = array_map([$this, 'cleanHeader'], $rawHeaders);

        $headerCount = array_count_values($headers);
        $headerIndexes = array_fill_keys($headers, 0);

        foreach ($headers as $key => $header) {
            $header = $this->removeUtf8Bom($header);
            $header = trim($header);

            if ($headerCount[$header] > 1) {
                $headers[$key] = $header . '_' . (++$headerIndexes[$header]);
            }
        }

        $data = [];
        while (($row = fgetcsv($file, 0, $separator)) !== false) {
            $data[] = array_combine($headers, $row);
        }
    
        fclose($file);

        return $data;
    }

    private function removeUtf8Bom(string $text): string
    {
        $bom = pack('H*','EFBBBF');
        // Kontrollera om texten börjar med BOM
        if (substr($text, 0, strlen($bom)) === $bom) {
            // Ta bort BOM genom att klippa bort de första tre byten
            $text = substr($text, strlen($bom));
        }
        return $text;
    }
    
    private function cleanHeader(string $header): string
    {
        // Ta bort UTF-8 BOM
        $header = $this->removeUtf8Bom($header);
    
        // Ta bort icke utskrivbara tecken och whitespace
        $header = preg_replace('/[\x00-\x1F\x7F]/u', '', $header);
        if ($header === null) {
            throw new Exception('Failed to clean header');
        }

        $header = trim($header);
    
        return $header;
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
        if (preg_match('/^-?\d+\.\d{2,}$/', $value)) {
            $value = (float) $value;
        } else {
            $value = str_replace(' ', '', $value);
            $value = str_replace(',', '.', str_replace('.', '', $value));

            $value = (float) $value;
        }

        return round($value, $numberOfDecimals);
    }
}
