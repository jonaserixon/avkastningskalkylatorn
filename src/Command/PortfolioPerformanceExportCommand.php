<?php

namespace src\Command;

use src\Enum\Bank;
use src\Service\FileManager\PPExporter;
use src\Service\Transaction\TransactionLoader;
use stdClass;

class PortfolioPerformanceExportCommand extends CommandProcessor
{
    /** @var mixed[] */
    private array $options;

    /**
     * @param mixed[] $options
     */
    public function __construct(array $options)
    {
        $this->options = $options;

        parent::__construct();
    }

    public function getParsedOptions(): stdClass
    {
        $commandOptions = $this->commands['transaction']['options'];

        $options = new stdClass();
        $options->exportCsv = $this->options['export-csv'] ?? $commandOptions['export-csv']['default'];
        $options->bank = $this->options['bank'] ?? null;
        $options->isin = $this->options['isin'] ?? null;
        $options->asset = $this->options['asset'] ?? null;
        $options->dateFrom = $this->options['date-from'] ?? null;
        $options->dateTo = $this->options['date-to'] ?? null;
        $options->account = $this->options['account'] ?? null;
        $options->displayLog = $this->options['display-log'] ?? $commandOptions['display-log']['default'];

        return $options;
    }

    public function execute(): void
    {
        $options = $this->getParsedOptions();

        $transactionLoader = new TransactionLoader(
            $options->bank,
            $options->isin,
            $options->asset,
            $options->dateFrom,
            $options->dateTo,
            false,
            $options->account
        );

        $transactions = $transactionLoader->getTransactions();
        // $assets = $transactionLoader->getFinancialAssets($transactions);

        $ppExporter = new PPExporter($transactions, $options->exportCsv);

        $bank = Bank::tryFrom(mb_strtoupper($options->bank));
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
            $ppExporter->exportNordnetDividends();
            $ppExporter->exportNordnetAccountTransactions();
            $ppExporter->exportNordnetPortfolioTransactions();
            $ppExporter->exportNordnetFees();

            $ppExporter->exportAvanzaPortfolioTransactions();
            $ppExporter->exportAvanzaAccountTransactions();
            $ppExporter->exportAvanzaDividends();
            $ppExporter->exportAvanzaFees();
        }
    }
}
