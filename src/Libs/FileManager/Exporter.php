<?php

namespace src\Libs\FileManager;

// TODO: this should be able to export "anything". So the columns should be dynamic.The name of the export should be export_<command>_<date>.csv

class Exporter
{
    public static function exportToCsv(array $headers, array $data, string $fileName = 'export', string $delimiter = ','): void
    {
        if (empty($headers) || empty($data)) {
            return;
        }

        $filePath = '/exports/' . $fileName . '_' . date('Y-m-d_His') . '.csv';

        $file = fopen($filePath, 'w');
        fputcsv($file, $headers, $delimiter);
    
        foreach ($data as $row) {
            fputcsv($file, $row, $delimiter);
        }
    
        fclose($file);
    }

//     public static function testGenerateCsvExport(array $transactions): void
//     {
        // $filePath = "/exports/export_".date('Y-m-d_His').".csv";
        // $csvHeaders = [
        //     'date',
        //     'amount'
        // ];
        // $f = fopen($filePath, "w");
        // fputcsv($f, $csvHeaders, ',');

        // foreach ($transactions as $transaction) {
        //     $row = [
        //         'date' => $transaction->date,
        //         'amount' => $transaction->amount,
        //     ];

        //     fputcsv($f, array_values($row), ',');
        // }
    // }
}
