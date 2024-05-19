<?php

namespace src\Libs;

use Exception;
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
    private ?string $bank;
    private ?string $isin;

    private Presenter $presenter;
    private TransactionHandler $transactionHandler;

    public function __construct(bool $exportCsv = false, bool $verbose = false, ?string $bank = null, ?string $isin = null)
    {
        $this->exportCsv = $exportCsv;
        $this->verbose = $verbose;
        $this->bank = $bank;
        $this->isin = $isin;

        $this->presenter = new Presenter();
        $this->transactionHandler = new TransactionHandler($this->presenter);
    }

    public function init()
    {
        $stockPrice = new StockPrice();
        $summaries = $this->transactionHandler->getTransactionsOverview($this->getTransactions());

        if ($this->exportCsv) {
            Exporter::generateCsvExport($summaries, $stockPrice);
            // Exporter::testGenerateCsvExport($this->transactionHandler->overview->transactions);
        }

        ob_start();
        $this->presentResult($summaries, $stockPrice);
        ob_end_flush();

        // TODO this should be placed elsewhere
        $this->transactionHandler->overview->addFinalTransaction($this->transactionHandler->overview->totalCurrentHoldings);
        $xirr = $this->transactionHandler->overview->calculateXIRR($this->transactionHandler->overview->transactions);

        echo "XIRR: " . ($xirr * 100) . '%' . PHP_EOL;
    }

    public function calculateCurrentHoldings()
    {
        $stockPrice = new StockPrice();
        $summaries = $this->transactionHandler->getTransactionsOverview($this->getTransactions());

        $result = [];
        foreach ($summaries as $summary) {
            $currentPricePerShare = $stockPrice->getCurrentPriceByIsin($summary->isin);

            if ($currentPricePerShare) {
                $result[] = $summary;
            }
        }

        $this->presentResult($result, $stockPrice);
    }

    private function getTransactions(): array
    {
        $transactions = array_merge(
            (new Avanza())->parseBankTransactions(),
            (new Nordnet())->parseBankTransactions()
        );

        if ($this->bank) {
            $bank = $this->bank;
            $transactions = array_filter($transactions, function ($transaction) use ($bank) {
                return $transaction->bank === mb_strtoupper($bank);
            });
        }
        if ($this->isin) {
            $isin = $this->isin;
            $transactions = array_filter($transactions, function ($transaction) use ($isin) {
                return $transaction->isin === mb_strtoupper($isin);
            });
        }

        if (empty($transactions)) {
            throw new Exception('No transactions found');
        }

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
                $this->transactionHandler->overview->totalCurrentHoldings += $currentValueOfShares;

                $this->transactionHandler->overview->addFinalCompanyTransaction($summary->isin, $currentValueOfShares);
            }

            $calculatedReturns = $this->calculateReturns($summary, $currentValueOfShares);

            if ($this->verbose) {
                $this->presenter->displayVerboseFormattedSummary($summary, $currentPricePerShare, $currentValueOfShares, $currentHoldingsMissingPricePerShare, $calculatedReturns);
            } else {
                $this->presenter->displayCompactFormattedSummary($summary, $calculatedReturns);
            }
        }

        echo PHP_EOL . $this->presenter->createSeparator('*') . PHP_EOL;
        echo PHP_EOL;

        foreach ($currentHoldingsMissingPricePerShare as $companyMissingPrice) {
            echo $this->presenter->blueText('Info: Kurspris saknas för ' . $companyMissingPrice) . PHP_EOL;
        }

        echo PHP_EOL;
    }

    public function calculateReturns(TransactionSummary $summary, ?float $currentValueOfShares): ?stdClass
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

        $result = new stdClass();
        $result->totalReturnExclFees = $totalReturnExclFees;
        $result->totalReturnExclFeesPercent = $totalReturnExclFeesPercent;
        $result->totalReturnInclFees = $totalReturnInclFees;
        $result->totalReturnInclFeesPercent = $totalReturnInclFeesPercent;

        return $result;
    }
}
