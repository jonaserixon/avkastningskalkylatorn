<?php

namespace src\Command;

use DateTime;
use Exception;
use src\Command\CommandProcessor;
use src\DataStructure\Transaction;
use src\Enum\Bank;
use src\Enum\TransactionType;
use src\Service\ProfitCalculator;
use src\Service\Transaction\TransactionLoader;
use src\Service\Transaction\TransactionMapper;
use src\Service\Utility;
use src\View\Logger;
use src\View\TextColorizer;
use stdClass;

class CalculateProfitCommand extends CommandProcessor
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
        $commandOptions = $this->commands['calculate']['options'];

        $options = new stdClass();
        $options->verbose = $this->options['verbose'] ?? $commandOptions['verbose']['default'];
        // $options->exportCsv = $this->options['export-csv'] ?? $commandOptions['export-csv']['default'];
        $options->bank = $this->options['bank'] ?? null;
        $options->isin = $this->options['isin'] ?? null;
        $options->asset = $this->options['asset'] ?? null;
        $options->dateFrom = $this->options['date-from'] ?? null;
        $options->dateTo = $this->options['date-to'] ?? null;
        $options->currentHoldings = $this->options['current-holdings'] ?? $commandOptions['current-holdings']['default'];
        $options->overview = $this->options['overview'] ?? $commandOptions['overview']['default'];
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

        $transactionLoader->overview->firstTransactionDate = '';
        $transactionLoader->overview->lastTransactionDate = '';
        $transactionMapper = new TransactionMapper($transactionLoader->overview);

        $portfolioFile = Utility::getLatestModifiedFile(ROOT_PATH . '/resources/portfolio', 'json');
        if ($portfolioFile === null) {
            throw new Exception('No portfolio file found.');
        }

        $portfolio = file_get_contents($portfolioFile);
        if ($portfolio === false) {
            throw new Exception('Failed to read portfolio file.');
        }

        $portfolio = json_decode($portfolio);

        $assets = [];
        foreach ($portfolio->portfolioTransactions as $row) {
            foreach ($row->transactions as &$transactionRow) {
                $transaction = new Transaction(
                    new DateTime($transactionRow->date->date),
                    Bank::from($transactionRow->bank),
                    $transactionRow->account,
                    TransactionType::from($transactionRow->type),
                    $transactionRow->name,
                    $transactionRow->description,
                    $transactionRow->rawQuantity,
                    $transactionRow->rawPrice,
                    $transactionRow->pricePerShareSEK,
                    $transactionRow->rawAmount,
                    $transactionRow->commission,
                    $transactionRow->currency,
                    $transactionRow->isin,
                    $transactionRow->exchangeRate
                );

                $transactionRow = $transaction;
            }
            $transactions = $transactionLoader->filterTransactions($row->transactions);
            $asset = $transactionMapper->_addTransactionsToAsset($row->isin, $row->name, $transactions);
            $assets[] = $asset;
        }

        foreach ($portfolio->accountTransactions as $row) {
            $transaction = new Transaction(
                new DateTime($row->date->date),
                Bank::from($row->bank),
                $row->account,
                TransactionType::from($row->type),
                $row->name,
                $row->description,
                $row->rawQuantity,
                $row->rawPrice,
                $row->pricePerShareSEK,
                $row->rawAmount,
                $row->commission,
                $row->currency,
                $row->isin,
                $row->exchangeRate
            );
            $transactions = $transactionLoader->filterTransactions([$transaction]);

            if (empty($transactions)) {
                continue;
            }

            $transactionMapper->handleNonAssetTransactionType($transactions[0]);
        }
        
        /*
        $assets = $transactionLoader->getFinancialAssets($transactionLoader->getTransactions());
        */

        $profitCalculator = new ProfitCalculator($options->currentHoldings);
        $result = $profitCalculator->calculate($assets, $transactionLoader->overview);

        if ($options->verbose) {
            $this->presenter->displayDetailedAssets($result->assets);
        } else {
            $this->presenter->generateAssetTable($result->overview, $result->assets);
        }

        // $this->presenter->displayFinancialOverview($result->overview);

        if (!empty($result->overview->currentHoldingsWeighting)) {
            $weightings = array_values($result->overview->currentHoldingsWeighting);

            if (!empty($weightings)) {
                echo PHP_EOL . TextColorizer::backgroundColor('Portföljviktning: ', 'pink', 'black') . PHP_EOL. PHP_EOL;

                $maxValue = max($weightings);
                foreach ($result->overview->currentHoldingsWeighting as $isin => $weight) {
                    $this->presenter->printRelativeProgressBar($isin, $weight, $maxValue);
                }
            }
        }

        if ($options->overview) {
            $this->presenter->displayInvestmentReport($result->overview, $result->assets);
        }

        foreach ($result->currentHoldingsMissingPricePerShare as $companyMissingPrice) {
            Logger::getInstance()->addInfo('Kurspris saknas för ' . $companyMissingPrice);
        }

        $this->presenter->displayAssetNotices($result->assets);

        Logger::getInstance()->printInfos();

        if ($options->displayLog) {
            Logger::getInstance()
                ->printNotices()
                ->printWarnings();
        }
    }
}
