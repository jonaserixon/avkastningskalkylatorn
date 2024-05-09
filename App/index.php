<?php
declare(strict_types=1);

require_once 'AutoLoad.php';

use App\Libs\ProfitCalculator;

define('ROOT_PATH', dirname(__DIR__));  // Anpassa detta beroende på var index.php ligger
define('IMPORT_DIR', ROOT_PATH . '/imports');
define('EXPORT_DIR', ROOT_PATH . '/exports');

$generateCsv = getenv('GENERATE_CSV') === 'yes' ? true : false;

$profitCalculator = new ProfitCalculator($generateCsv);
$profitCalculator->init();
