#!/usr/bin/env php
<?php declare(strict_types=1);

require_once 'AutoLoad.php';

use Avk\Command\CommandProcessor;

define('ROOT_PATH', dirname(__DIR__));
define('IMPORT_DIR', ROOT_PATH . '/resources/imports');
define('STOCK_PRICE_DIR', IMPORT_DIR . '/stock_price');
define('EXPORT_DIR', ROOT_PATH . '/resources/exports');

try {
    $directories = [
        ROOT_PATH . '/resources',
        IMPORT_DIR,
        IMPORT_DIR . '/banks',
        IMPORT_DIR . '/banks/avanza',
        IMPORT_DIR . '/banks/nordnet',
        IMPORT_DIR . '/banks/custom',
        STOCK_PRICE_DIR,
        EXPORT_DIR
    ];

    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    (new CommandProcessor())->main($_SERVER['argv']);
} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}
