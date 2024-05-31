<?php

namespace src\Command;

use src\Enum\TransactionType;
use src\Service\FileManager\Exporter;
use src\Service\ProfitCalculator;
use src\Service\Transaction\TransactionLoader;
use src\View\Logger;
use src\View\TextColorizer;
use stdClass;

class TransactionCommand extends CommandProcessor
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
        $options->currentHoldings = $this->options['current-holdings'] ?? $commandOptions['current-holdings']['default'];
        $options->cashFlow = $this->options['cash-flow'] ?? null;
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
            $options->currentHoldings,
            $options->account
        );

        $transactions = $transactionLoader->getTransactions();

        if ($options->cashFlow) {
            $assets = $transactionLoader->getFinancialAssets($transactions);

            $profitCalculator = new ProfitCalculator($options->currentHoldings);
            $result = $profitCalculator->calculate($assets, $transactionLoader->overview);

            foreach ($result->overview->cashFlows as $cashFlow) {
                $res = $cashFlow->getDateString() . ' | ';
                $res .= TextColorizer::colorText($cashFlow->getBankValue(), 'grey') . ' | ';
                $res .= TextColorizer::colorText($cashFlow->getAccount(), 'green') . ' | ';
                $res .= TextColorizer::colorText($cashFlow->getName(), 'pink') . ' | ';
                $res .= TextColorizer::colorText($cashFlow->getTypeValue(), 'yellow') . ' | ';
                $res .= TextColorizer::colorText($this->presenter->formatNumber($cashFlow->getRawAmount()), 'cyan');

                echo $res . PHP_EOL;
            }

            if ($options->exportCsv) {
                $cashFlowArray = [];
                foreach ($result->overview->cashFlows as $cashFlow) {
                    $amount = $cashFlow->getRawAmount();
                    if ($cashFlow->getTypeValue() === 'deposit') {
                        $amount = $amount * -1;
                    } elseif ($cashFlow->getTypeValue() === 'withdrawal') {
                        $amount = abs($amount);
                    }
                    if (!in_array($cashFlow->getType(), [
                        TransactionType::DEPOSIT,
                        TransactionType::WITHDRAWAL,
                        TransactionType::DIVIDEND,
                        TransactionType::CURRENT_HOLDING,
                        TransactionType::FEE,
                        TransactionType::FOREIGN_WITHHOLDING_TAX,
                        TransactionType::RETURNED_FOREIGN_WITHHOLDING_TAX
                    ])) {
                        continue;
                    }
                    $cashFlowArray[] = [
                        $cashFlow->getDateString(),
                        $cashFlow->getBankValue(),
                        $cashFlow->getAccount(),
                        $cashFlow->getName(),
                        $cashFlow->getTypeValue(),
                        $amount
                    ];
                }
                $headers = ['Datum', 'Bank', 'Konto', 'Namn', 'Typ', 'Belopp'];
                Exporter::exportToCsv($headers, $cashFlowArray, 'cash_flow');
            }
        } else {
            echo 'Datum | Bank | Konto | Namn | Typ | Belopp | Antal | Pris' . PHP_EOL;
            foreach ($transactions as $transaction) {
                $res = $transaction->getDateString() . ' | ';
                $res .= TextColorizer::colorText($transaction->getBankValue(), 'grey') . ' | ';
                $res .= TextColorizer::colorText($transaction->getAccount(), 'green') . ' | ';
                $res .= TextColorizer::colorText($transaction->getName() . " ({$transaction->getIsin()})", 'pink') . ' | ';
                $res .= TextColorizer::colorText($transaction->getTypeValue(), 'yellow') . ' | ';
                $res .= TextColorizer::colorText($this->presenter->formatNumber($transaction->getRawAmount()), 'cyan') . ' | ';
                $res .= TextColorizer::colorText($this->presenter->formatNumber($transaction->getRawQuantity()), 'grey') . ' | ';
                $res .= TextColorizer::backgroundColor($this->presenter->formatNumber($transaction->getRawPrice()), 'green');

                echo $res . PHP_EOL;
            }
        }

        Logger::getInstance()->printInfos();

        if ($options->displayLog) {
            Logger::getInstance()
                ->printNotices()
                ->printWarnings();
        }
    }
}
