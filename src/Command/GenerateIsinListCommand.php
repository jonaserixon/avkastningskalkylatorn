<?php

declare(strict_types=1);

namespace Avk\Command;

use Avk\Enum\CommandOptionName;
use Avk\Service\FileManager\Exporter;
use Avk\Service\Transaction\TransactionLoader;

class GenerateIsinListCommand extends CommandBase
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

        $assets = $transactionLoader->getFinancialAssets($transactionLoader->getTransactions());

        $isinList = [];
        foreach ($assets as $asset) {
            $isinList[] = [
                'name' => $asset->name,
                'isin' => $asset->isin
            ];
        }

        Exporter::exportToCsv(['name', 'isin'], $isinList, 'isin_list');
    }
}
