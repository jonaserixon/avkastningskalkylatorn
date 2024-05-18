#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once 'AutoLoad.php';

use src\Libs\ProfitCalculator;

define('ROOT_PATH', dirname(__DIR__));
define('IMPORT_DIR', ROOT_PATH . '/imports');
define('STOCK_PRICE_DIR', IMPORT_DIR . '/stock_price');
define('EXPORT_DIR', ROOT_PATH . '/exports');

$availableCommands = [
    'calculateProfit' => [
        'description' => 'A fitting description for the command',
        'options' => [
            'generateCsv' => [
                'description' => 'Generate CSV file',
                'default' => 'no'
            ]
        ]
    ],
];

function main(array $argv): void
{
    if (empty($argv)) {
        echo "Usage: avk <command> [options]\n";
        echo "Available commands: x, y and z\n";
        exit(1);
    }

    $command = $argv[1];

    // Definiera tillåtna optioner för varje kommando
    $allowedOptions = [
        'calculateProfit' => ['export-csv', 'asset:', 'isin:'],
        'hello' => ['name:']
    ];

    // Kontrollera om kommandot är känt
    if (!array_key_exists($command, $allowedOptions)) {
        echo "Unknown command: $command\n";
        echo "Available commands: x, y and z\n";
        exit(1);
    }

    // Använd getopt för att analysera flaggor
    $options = getopt("", $allowedOptions[$command]);

    $command = $argv[1];
    switch ($command) {
        case 'calculateProfit':
            $generateCsv = false;

            $profitCalculator = new ProfitCalculator($generateCsv);
            $profitCalculator->init();

            break;
        case 'hello':
            if (isset($options['name'])) {
                echo $options['name'];
            } else {
                echo "Usage: avk hello [name]\n";
            }
            break;
        default:
            echo "Unknown command: $command\n";
            echo "Available commands: x, y and z\n";
            break;
    }
}

try {
    main($_SERVER['argv']);
} catch (Exception $e) {
    echo $e->getMessage();
}
