<?php

namespace src\Libs\Command;

use src\Libs\ProfitCalculator;
use src\Libs\Transaction\TransactionLoader;
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
        $options->bank = $this->options['bank'] ?? null;
        $options->isin = $this->options['isin'] ?? null;
        $options->asset = $this->options['asset'] ?? null;
        $options->dateFrom = $this->options['date-from'] ?? null;
        $options->dateTo = $this->options['date-to'] ?? null;
        $options->currentHoldings = $this->options['current-holdings'] ?? $commandOptions['current-holdings']['default'];
        $options->cashFlow = $this->options['cash-flow'] ?? null;

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
            $options->currentHoldings
        );

        $transactions = $transactionLoader->getTransactions();

        if ($options->cashFlow) {
            $assets = $transactionLoader->getFinancialAssets($transactions);

            $profitCalculator = new ProfitCalculator($this->presenter, $options->currentHoldings);
            $result = $profitCalculator->calculate($assets, $transactionLoader->overview);

            foreach ((array) $result->overview->cashFlows as $cashFlow) {
                $res = $cashFlow->getDateString() . ' | ';
                $res .= $this->presenter->greyText($cashFlow->getBank()) . ' | ';
                $res .= $this->presenter->greenText($cashFlow->getAccount()) . ' | ';
                $res .= $this->presenter->pinkText($cashFlow->getName()) . ' | ';
                $res .= $this->presenter->yellowText($cashFlow->getType()) . ' | ';
                $res .= $this->presenter->cyanText($this->presenter->formatNumber($cashFlow->getRawAmount()));

                echo $res . PHP_EOL;
            }
        } else {
            foreach ($transactions as $transaction) {
                $res = $transaction->getDateString() . ' | ';
                $res .= $this->presenter->greyText($transaction->getBank()) . ' | ';
                $res .= $this->presenter->greenText($transaction->getAccount()) . ' | ';
                $res .= $this->presenter->pinkText($transaction->getName()) . ' | ';
                $res .= $this->presenter->yellowText($transaction->getType()) . ' | ';
                $res .= $this->presenter->cyanText($this->presenter->formatNumber($transaction->getRawAmount()));

                echo $res . PHP_EOL;
            }
        }
    }
}
