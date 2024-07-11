<?php declare(strict_types=1);

namespace Avk\Command;

use Avk\Enum\Bank;
use Avk\Enum\CommandOptionName;
use Avk\Service\FileManager\PPExporter;
use Avk\Service\Transaction\TransactionLoader;
use Avk\View\Logger;

class PortfolioPerformanceExportCommand extends CommandBase
{
    public function execute(): void
    {
        $transactionLoader = new TransactionLoader(
            $this->command->getOption(CommandOptionName::BANK)->value,
            $this->command->getOption(CommandOptionName::ISIN)->value,
            $this->command->getOption(CommandOptionName::ASSET)->value,
            $this->command->getOption(CommandOptionName::DATE_FROM)->value,
            $this->command->getOption(CommandOptionName::DATE_TO)->value,
            false,
            $this->command->getOption(CommandOptionName::ACCOUNT)->value
        );

        $transactions = $transactionLoader->getTransactions();

        // $assets = $transactionLoader->getFinancialAssets($transactions);

        $ppExporter = new PPExporter($transactions, $this->command->getOption(CommandOptionName::EXPORT_CSV)->value);

        if (!$this->command->getOption(CommandOptionName::BANK)->value) {
            Logger::getInstance()->addWarning('Bank not provided');
        } else {
            $bank = Bank::tryFrom(mb_strtoupper($this->command->getOption(CommandOptionName::BANK)->value));
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
