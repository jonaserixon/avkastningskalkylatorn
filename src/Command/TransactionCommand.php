<?php declare(strict_types=1);

namespace Avk\Command;

use Avk\Enum\CommandOptionName;
use Avk\Handler\TransactionHandler;
use Avk\Service\Transaction\TransactionLoader;
use Avk\View\Logger;

class TransactionCommand extends CommandBase
{
    public function execute(): void
    {
        $transactionLoader = new TransactionLoader(
            $this->command->getOption(CommandOptionName::BANK)->value,
            $this->command->getOption(CommandOptionName::ISIN)->value,
            $this->command->getOption(CommandOptionName::ASSET)->value,
            $this->command->getOption(CommandOptionName::DATE_FROM)->value,
            $this->command->getOption(CommandOptionName::DATE_TO)->value,
            $this->command->getOption(CommandOptionName::CURRENT_HOLDINGS)->value,
            $this->command->getOption(CommandOptionName::ACCOUNT)->value
        );

        $handler = new TransactionHandler($this->presenter, $transactionLoader);

        $handler->updatePortfolio();

        // $handler->updateHistoricalPrices($this->command->getOption(CommandOptionName::ISIN)->value);

        if ($this->command->getOption(CommandOptionName::CASH_FLOW_DATES)->value) {
            $handler->getCashFlowDates(
                $this->command->getOption(CommandOptionName::CURRENT_HOLDINGS)->value
            );
        } elseif ($this->command->getOption(CommandOptionName::CASH_FLOW)->value) {
            $handler->getCashFlows(
                $this->command->getOption(CommandOptionName::CURRENT_HOLDINGS)->value,
                $this->command->getOption(CommandOptionName::EXPORT_CSV)->value
            );
        } else {
            $handler->displayTransactions();
        }

        Logger::getInstance()->printInfos();

        if ($this->command->getOption(CommandOptionName::DISPLAY_LOG)->value) {
            Logger::getInstance()
                ->printNotices()
                ->printWarnings();
        }
    }
}
