<?php
declare(strict_types=1);

require_once 'AutoLoad.php';

use App\Libs\ProfitCalculator;

$profitCalculator = new ProfitCalculator();
$profitCalculator->init();
