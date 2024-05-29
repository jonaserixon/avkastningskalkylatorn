<?php

namespace src\Libs\FileManager;

use Exception;

class Exporter
{
    /**
     * @param string[] $headers
     * @param mixed[] $data
     */
    public static function exportToCsv(array $headers, array $data, string $fileName = 'export', string $delimiter = ','): void
    {
        if (empty($headers) || empty($data)) {
            return;
        }

        $filePath = '/exports/' . $fileName . '_' . date('Y-m-d_His') . '.csv';

        $file = fopen($filePath, 'w');
        if ($file === false) {
            throw new Exception('Failed to open file: ' . basename($filePath));
        }
        fputcsv($file, $headers, $delimiter);

        foreach ($data as $row) {
            fputcsv($file, $row, $delimiter);
        }

        fclose($file);

        echo 'Exported to ' . $filePath . PHP_EOL;
    }
}
