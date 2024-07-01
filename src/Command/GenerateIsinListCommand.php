<?php declare(strict_types=1);

namespace Avk\Command;

use Avk\DataStructure\Command;
use Avk\Service\FileManager\Exporter;
use Avk\Service\Transaction\TransactionLoader;
use Avk\View\Presenter;
use stdClass;

class GenerateIsinListCommand extends CommandBase
{
    public function execute(): void
    {
        $transactionLoader = new TransactionLoader(
            $this->command->getOption('bank')->value,
            $this->command->getOption('isin')->value,
            $this->command->getOption('asset')->value,
            $this->command->getOption('date-from')->value,
            $this->command->getOption('date-to')->value,
            $this->command->getOption('current-holdings')->value,
            $this->command->getOption('account')->value
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
