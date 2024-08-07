<?php

declare(strict_types=1);

namespace Avk\Command;

use Avk\Handler\GenerateTickerListHandler;
use Avk\Service\Transaction\TransactionLoader;

class GenerateTickerListCommand extends CommandBase
{
    public function execute(): void
    {
        $transactionLoader = new TransactionLoader();
        $assets = $transactionLoader->getFinancialAssets($transactionLoader->getTransactions());

        $handler = new GenerateTickerListHandler();
        $handler->tickerPicker($assets);
    }
}
