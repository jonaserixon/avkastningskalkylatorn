#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once 'AutoLoad.php';

use src\Libs\CommandProcessor;

define('ROOT_PATH', dirname(__DIR__));
define('IMPORT_DIR', ROOT_PATH . '/imports');
define('STOCK_PRICE_DIR', IMPORT_DIR . '/stock_price');
define('EXPORT_DIR', ROOT_PATH . '/exports');

try {
    (new CommandProcessor())->main($_SERVER['argv']);
} catch (Exception $e) {
    echo $e->getMessage();
}
