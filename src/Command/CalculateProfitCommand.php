<?php declare(strict_types=1);

namespace Avk\Command;

use Avk\Command\CommandProcessor;
use Avk\DataStructure\Command;
use Avk\DataStructure\Transaction;
use Avk\Enum\Bank;
use Avk\Enum\TransactionType;
use Avk\Service\Performance\TimeWeightedReturn;
use Avk\Service\ProfitCalculator;
use Avk\Service\Transaction\TransactionLoader;
use Avk\Service\Transaction\TransactionMapper;
use Avk\Service\Utility;
use Avk\View\Logger;
use Avk\View\Presenter;
use Avk\View\TextColorizer;
use DateTime;
use Exception;

class CalculateProfitCommand extends CommandBase
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

        // if ($options->TWR) {
        if ($this->command->getOption('twr')->value) {
            $profitCalculator = new TimeWeightedReturn($transactionMapper);
            $twrResult = $profitCalculator->calculate(
                $portfolio,
                // $options->dateFrom,
                $this->command->getOption('date-from')->value,
                $this->command->getOption('date-to')->value,
                $this->command->getOption('bank')->value
            );

            echo "\nSubperiod Returns:\n";
            foreach ($twrResult->returns as $index => $return) {
                echo 'Subperiod ' . ($index + 1) . ': ' . ($return * 100) . "%\n";
            }

            echo 'Total TWR: ' . ($twrResult->twr * 100) . '%';
        } else {
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
    
            $profitCalculator = new ProfitCalculator($this->command->getOption('current-holdings')->value);
            $result = $profitCalculator->calculate($assets, $transactionLoader->overview);
    
            if ($this->command->getOption('verbose')->value) {
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
    
            if ($this->command->getOption('overview')->value) {
                $this->presenter->displayInvestmentReport($result->overview, $result->assets);
            }
    
            foreach ($result->currentHoldingsMissingPricePerShare as $companyMissingPrice) {
                Logger::getInstance()->addInfo('Kurspris saknas för ' . $companyMissingPrice);
            }
    
            $this->presenter->displayAssetNotices($result->assets);
        }

        Logger::getInstance()->printInfos();

        if ($this->command->getOption('display-log')->value) {
            Logger::getInstance()
                ->printNotices()
                ->printWarnings();
        }
    }
}
