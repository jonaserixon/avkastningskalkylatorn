<?php declare(strict_types=1);

namespace Avk\Command;

use Avk\Enum\Bank;
use Avk\Service\FileManager\PPExporter;
use Avk\Service\Transaction\TransactionLoader;
use Avk\View\Logger;

class PortfolioPerformanceExportCommand extends CommandBase
{
    public function execute(): void
    {
        $transactionLoader = new TransactionLoader(
            $this->command->getOption('bank')->value,
            $this->command->getOption('isin')->value,
            $this->command->getOption('asset')->value,
            $this->command->getOption('date-from')->value,
            $this->command->getOption('date-to')->value,
            // $this->command->getOption('current-holdings')->value,
            false,
            $this->command->getOption('account')->value
        );

        $transactions = $transactionLoader->getTransactions();

        // $assets = $transactionLoader->getFinancialAssets($transactions);

        $ppExporter = new PPExporter($transactions, $this->command->getOption('export-csv')->value);

        if (!$this->command->getOption('bank')->value) {
            Logger::getInstance()->addWarning('Bank not provided');
        } else {
            $bank = Bank::tryFrom(mb_strtoupper($this->command->getOption('bank')->value));
            if ($bank === Bank::NORDNET) {
                $ppExporter->exportNordnetDividends();
                $ppExporter->exportNordnetAccountTransactions();
                $ppExporter->exportNordnetPortfolioTransactions();
                $ppExporter->exportNordnetFees();
            } elseif ($bank === Bank::AVANZA) {
                $ppExporter->exportAvanzaPortfolioTransactions();
                $ppExporter->exportAvanzaAccountTransactions();
                $ppExporter->exportAvanzaDividends();
                $ppExporter->exportAvanzaFees();
            } else {
                Logger::getInstance()->addWarning('Bank not supported');
            }
        }

        Logger::getInstance()
            ->printInfos()
            ->printNotices()
            ->printWarnings();
    }
}
