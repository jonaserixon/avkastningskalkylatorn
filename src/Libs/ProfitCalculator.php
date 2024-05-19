<?php

namespace src\Libs;

use Exception;
use src\DataStructure\AssetReturn;
use src\DataStructure\TransactionSummary;
use src\Libs\FileManager\Exporter;
use src\Libs\FileManager\Importer\Avanza;
use src\Libs\FileManager\Importer\Nordnet;
use src\Libs\FileManager\Importer\StockPrice;
use stdClass;

class ProfitCalculator
{
    private bool $exportCsv;
    private bool $verbose;
    private ?string $filterBank;
    private ?string $filterIsin;
    private ?string $filterAsset;
    private ?string $filterDateFrom;
    private ?string $filterDateTo;
    private bool $filterCurrentHoldings;

    private Presenter $presenter;
    private TransactionParser $transactionParser;

    public function __construct(
        bool $exportCsv,
        bool $verbose,
        ?string $bank,
        ?string $isin,
        ?string $asset,
        ?string $dateFrom,
        ?string $dateTo,
        bool $currentHoldings
    ) {
        $this->exportCsv = $exportCsv;
        $this->verbose = $verbose;
        $this->filterBank = $bank;
        $this->filterIsin = $isin;
        $this->filterAsset = $asset;
        $this->filterDateFrom = $dateFrom;
        $this->filterDateTo = $dateTo;
        $this->filterCurrentHoldings = $currentHoldings;

        $this->presenter = new Presenter();
        $this->transactionParser = new TransactionParser($this->presenter);
    }

    public function init()
    {
        $stockPrice = new StockPrice();
        $summaries = $this->transactionParser->getTransactionsOverview($this->getTransactions());

        if ($this->exportCsv) {
            // Exporter::generateCsvExport($summaries, $stockPrice);
            // Exporter::testGenerateCsvExport($this->transactionParser->overview->transactions);
        }

        ob_start();
        $this->presentResult($summaries, $stockPrice);
        ob_end_flush();

        // TODO this should be placed elsewhere
        $this->transactionParser->overview->addFinalTransaction($this->transactionParser->overview->totalCurrentHoldings);
        $xirr = $this->transactionParser->overview->calculateXIRR($this->transactionParser->overview->transactions);

        echo 'Tot. avgifter: ' . $this->presenter->colorPicker($this->transactionParser->overview->totalFee) . ' SEK' . PHP_EOL;
        echo 'Tot. utdelningar: ' . $this->presenter->colorPicker($this->transactionParser->overview->totalDividend) . ' SEK' . PHP_EOL;
        echo 'Tot. köpbelopp: ' . $this->presenter->colorPicker($this->transactionParser->overview->totalBuyAmount) . ' SEK' . PHP_EOL;
        echo 'Tot. säljbelopp: ' . $this->presenter->colorPicker($this->transactionParser->overview->totalSellAmount) . ' SEK' . PHP_EOL;
        echo 'Tot. nuvarande innehav: ' . $this->presenter->colorPicker($this->transactionParser->overview->totalCurrentHoldings) . ' SEK' . PHP_EOL;
        echo 'Tot. avkastning: ' . $this->presenter->colorPicker($this->transactionParser->overview->totalProfitInclFees) . ' SEK' . PHP_EOL;
        echo 'XIRR: ' . $this->presenter->colorPicker($xirr * 100) . '%' . PHP_EOL;
    }

    /*
    public function _calculate()
    {
        $stockPrice = new StockPrice();
        $summaries = $this->transactionParser->getTransactionsOverview($this->getTransactions());

        if ($this->exportCsv) {
            // Exporter::generateCsvExport($summaries, $stockPrice);
            // Exporter::testGenerateCsvExport($this->transactionParser->overview->transactions);
        }

        $stockPrice = new StockPrice();
        $currentHoldingsMissingPricePerShare = [];
        foreach ($summaries as $summary) {
            $currentPricePerShare = $stockPrice->getCurrentPriceByIsin($summary->isin);

            if ($currentPricePerShare) {
                $currentValueOfShares = $summary->currentNumberOfShares * $currentPricePerShare;

                $this->transactionParser->overview->totalCurrentHoldings += $currentValueOfShares;
                $this->transactionParser->overview->addFinalAssetTransaction($summary->isin, $currentValueOfShares);
                $this->transactionParser->overview->addFinalTransaction($currentValueOfShares);

                $summary->currentPricePerShare = $currentPricePerShare;
                $summary->currentValueOfShares = $currentValueOfShares;
            } else {
                $currentHoldingsMissingPricePerShare[] = $summary->name . ' (' . $summary->isin . ')';
            }

            $summary->assetReturn = $this->calculateReturnsOnAsset($summary, $currentValueOfShares);
        }

        $result = new stdClass();
        $result->currentHoldingsMissingPricePerShare = $currentHoldingsMissingPricePerShare;
        $result->summaries = $summaries;
        $result->overview = $this->transactionParser->overview;
        $result->xirr = $this->transactionParser->overview->calculateXIRR($this->transactionParser->overview->transactions);

        return $result;
    }
    */

    public function calculate(): stdClass
    {
        $summaries = $this->transactionParser->getTransactionsOverview($this->getTransactions());

        $stockPrice = new StockPrice();
        $currentHoldingsMissingPricePerShare = [];
        $filteredSummaries = [];
        foreach ($summaries as $summary) {
            if ($this->filterCurrentHoldings && $summary->currentNumberOfShares <= 0) {
                continue;
            }

            $currentPricePerShare = $stockPrice->getCurrentPriceByIsin($summary->isin);

            if ($currentPricePerShare) {
                $currentValueOfShares = $summary->currentNumberOfShares * $currentPricePerShare;

                
                

                $this->transactionParser->overview->totalCurrentHoldings += $currentValueOfShares;
                $this->transactionParser->overview->addFinalAssetTransaction($summary->isin, $currentValueOfShares);
                $this->transactionParser->overview->addFinalTransaction($currentValueOfShares);

                $summary->currentPricePerShare = $currentPricePerShare;
                $summary->currentValueOfShares = $currentValueOfShares;

                $filteredSummaries[] = $summary;
            } elseif ($summary->currentNumberOfShares > 0 && !$currentPricePerShare) {
                $currentHoldingsMissingPricePerShare[] = $summary->name . ' (' . $summary->isin . ')';
            }

            $summary->assetReturn = $this->calculateReturnsOnAsset($summary, $currentValueOfShares);
        }

        if ($this->exportCsv) {
            // Exporter::generateCsvExport($filteredSummaries, $stockPrice);
            // Exporter::testGenerateCsvExport($this->transactionParser->overview->transactions);
        }

        $result = new stdClass();
        $result->currentHoldingsMissingPricePerShare = $currentHoldingsMissingPricePerShare;

        if ($this->filterCurrentHoldings) {
            $result->summaries = $filteredSummaries;
        } else {
            $result->summaries = $summaries;
        }

        $result->overview = $this->transactionParser->overview;
        $result->xirr = $this->transactionParser->overview->calculateXIRR($this->transactionParser->overview->transactions);

        return $result;
    }

    /**
     * Returns a list of sorted and possibly filtered transactions.
     */
    public function getTransactions(): array
    {
        $transactions = array_merge(
            (new Avanza())->parseBankTransactions(),
            (new Nordnet())->parseBankTransactions()
        );

        $filters = [
            'bank' => $this->filterBank,
            'isin' => $this->filterIsin,
            'asset' => $this->filterAsset,
            'dateFrom' => $this->filterDateFrom,
            'dateTo' => $this->filterDateTo
        ];

        foreach ($filters as $key => $value) {
            if ($value) {
                $value = mb_strtoupper($value);
                $transactions = array_filter($transactions, function ($transaction) use ($key, $value) {
                    if ($key === 'asset') {
                        return str_contains(mb_strtoupper($transaction->name), $value);
                    }

                    if ($key === 'dateFrom') {
                        return strtotime($transaction->date) >= strtotime($value);
                    }

                    if ($key === 'dateTo') {
                        return strtotime($transaction->date) <= strtotime($value);
                    }

                    return mb_strtoupper($transaction->{$key}) === $value;
                });
            }
        }

        if (empty($transactions)) {
            throw new Exception('No transactions found');
        }

        // Sort transactions by date, bank and ISIN.
        usort($transactions, function ($a, $b) {
            $dateComparison = strtotime($a->date) <=> strtotime($b->date);
            if ($dateComparison !== 0) {
                return $dateComparison;
            }
            $bankComparison = strcmp($a->bank, $b->bank);
            if ($bankComparison !== 0) {
                return $bankComparison;
            }
            return strcmp($a->isin, $b->isin);
        });

        return $transactions;
    }

    /**
     * @param TransactionSummary[] $summaries
     */
    protected function presentResult(array $summaries, StockPrice $stockPrice): void
    {
        echo $this->presenter->createSeparator('-') . PHP_EOL;

        $currentHoldingsMissingPricePerShare = [];
        foreach ($summaries as $summary) {
            $currentPricePerShare = $stockPrice->getCurrentPriceByIsin($summary->isin);

            // TODO: move calculations to a separate method

            $currentValueOfShares = null;
            if ($currentPricePerShare && $summary->currentNumberOfShares > 0) {
                $currentValueOfShares = $summary->currentNumberOfShares * $currentPricePerShare;
                $this->transactionParser->overview->totalCurrentHoldings += $currentValueOfShares;

                $this->transactionParser->overview->addFinalAssetTransaction($summary->isin, $currentValueOfShares);
            }

            $calculatedReturns = $this->calculateReturnsOnAsset($summary, $currentValueOfShares);

            // TODO: flytta allt med presenter till kommandot?
            // if ($this->verbose) {
            //     $this->presenter->displayVerboseFormattedSummary($summary, $currentPricePerShare, $currentValueOfShares, $currentHoldingsMissingPricePerShare, $calculatedReturns);
            // } else {
            //     $this->presenter->displayCompactFormattedSummary($summary, $calculatedReturns);
            // }
        }

        echo PHP_EOL . $this->presenter->createSeparator('*') . PHP_EOL;
        echo PHP_EOL;

        foreach ($currentHoldingsMissingPricePerShare as $companyMissingPrice) {
            echo $this->presenter->blueText('Info: Kurspris saknas för ' . $companyMissingPrice) . PHP_EOL;
        }

        echo PHP_EOL;
    }

    public function calculateReturnsOnAsset(TransactionSummary $summary, ?float $currentValueOfShares): ?AssetReturn
    {
        if ($currentValueOfShares === null) {
            $currentValueOfShares = 0;
        }

        if ($summary->buyAmountTotal <= 0) {
            return null;
        }

        $adjustedTotalBuyAmount = $summary->buyAmountTotal + $summary->feeBuyAmountTotal;
        $adjustedTotalSellAmount = $summary->sellAmountTotal + $summary->dividendAmountTotal - $summary->feeSellAmountTotal;

        // Beräkna total avkastning exklusive avgifter
        $totalReturnExclFees = $summary->sellAmountTotal + $summary->dividendAmountTotal + $currentValueOfShares - $summary->buyAmountTotal;

        $totalReturnExclFeesPercent = round($totalReturnExclFees / $summary->buyAmountTotal * 100, 2);

        // Beräkna total avkastning inklusive avgifter
        $totalReturnInclFees = $adjustedTotalSellAmount + $currentValueOfShares - $adjustedTotalBuyAmount;
        $totalReturnInclFeesPercent = round($totalReturnInclFees / $adjustedTotalBuyAmount * 100, 2);

        $result = new AssetReturn();
        $result->totalReturnExclFees = $totalReturnExclFees;
        $result->totalReturnExclFeesPercent = $totalReturnExclFeesPercent;
        $result->totalReturnInclFees = $totalReturnInclFees;
        $result->totalReturnInclFeesPercent = $totalReturnInclFeesPercent;

        $this->transactionParser->overview->totalProfitInclFees += $totalReturnInclFees;

        return $result;
    }
}
