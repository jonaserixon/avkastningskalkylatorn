#!/usr/bin/env php
<?php declare(strict_types=1);

require_once 'AutoLoad.php';

use Avk\Command\CommandProcessor;

define('ROOT_PATH', dirname(__DIR__));
define('IMPORT_DIR', ROOT_PATH . '/resources/imports');
define('STOCK_PRICE_DIR', IMPORT_DIR . '/stock_price');
define('EXPORT_DIR', ROOT_PATH . '/resources/exports');

try {
    if (!is_dir(ROOT_PATH . '/resources')) {
        mkdir(ROOT_PATH . '/resources', 0777, true);
    }
    if (!is_dir(IMPORT_DIR)) {
        mkdir(IMPORT_DIR, 0777, true);
    }
    if (!is_dir(IMPORT_DIR . '/stock_price')) {
        mkdir(IMPORT_DIR . '/stock_price', 0777, true);
    }
    if (!is_dir(IMPORT_DIR . '/banks')) {
        mkdir(IMPORT_DIR . '/banks', 0777, true);
    }
    if (!is_dir(IMPORT_DIR . '/avanza')) {
        mkdir(IMPORT_DIR . '/avanza', 0777, true);
    }
    if (!is_dir(IMPORT_DIR . '/nordnet')) {
        mkdir(IMPORT_DIR . '/nordnet', 0777, true);
    }
    if (!is_dir(STOCK_PRICE_DIR)) {
        mkdir(STOCK_PRICE_DIR, 0777, true);
    }
    if (!is_dir(EXPORT_DIR)) {
        mkdir(EXPORT_DIR, 0777, true);
    }

    (new CommandProcessor())->main($_SERVER['argv']);
} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}
