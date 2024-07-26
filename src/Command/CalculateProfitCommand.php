<?php declare(strict_types=1);

namespace Avk\Command;

use Avk\Enum\CommandOptionName;
use Avk\Handler\CalculateProfitHandler;
use Avk\Service\Transaction\TransactionLoader;
use Avk\View\Logger;

class CalculateProfitCommand extends CommandBase
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

        $handler = new CalculateProfitHandler($this->presenter, $transactionLoader);

        if ($this->command->getOption(CommandOptionName::TWR)->value) {
            $handler->calculateTwr(
                $this->command->getOption(CommandOptionName::DATE_FROM)->value,
                $this->command->getOption(CommandOptionName::DATE_TO)->value,
                $this->command->getOption(CommandOptionName::BANK)->value
            );
        } else {
            $handler->displayPerformance(
                $this->command->getOption(CommandOptionName::CURRENT_HOLDINGS)->value,
                $this->command->getOption(CommandOptionName::VERBOSE)->value,
                $this->command->getOption(CommandOptionName::OVERVIEW)->value
            );
        }

        Logger::getInstance()
            ->printInfos()
            ->printNotices()
            ->printWarnings();
    }
}
