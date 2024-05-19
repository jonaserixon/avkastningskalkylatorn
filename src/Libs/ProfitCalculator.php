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
    private ?string $asset;
    private ?string $dateFrom;
    private ?string $dateTo;

    private Presenter $presenter;
    private TransactionParser $transactionParser;

    public function __construct(
        bool $exportCsv = false,
        bool $verbose = false,
        ?string $bank = null,
        ?string $isin = null,
        ?string $asset = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ) {
        $this->exportCsv = $exportCsv;
        $this->verbose = $verbose;
        $this->bank = $bank;
        $this->isin = $isin;
        $this->asset = $asset;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;

        $this->presenter = new Presenter();
        $this->transactionParser = new TransactionParser($this->presenter);
    }

    public function init()
    {
        $stockPrice = new StockPrice();
        $summaries = $this->transactionParser->getTransactionsOverview($this->getTransactions());

        if ($this->exportCsv) {
            Exporter::generateCsvExport($summaries, $stockPrice);
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

    public function calculateCurrentHoldings()
    {
        $stockPrice = new StockPrice();
        $summaries = $this->transactionParser->getTransactionsOverview($this->getTransactions());

        $result = [];
        foreach ($summaries as $summary) {
            $currentPricePerShare = $stockPrice->getCurrentPriceByIsin($summary->isin);

            if ($currentPricePerShare) {
                $result[] = $summary;
            }
        }

        $this->presentResult($result, $stockPrice);

        // TODO this should be placed elsewhere
        $this->transactionParser->overview->addFinalTransaction($this->transactionParser->overview->totalCurrentHoldings);
        $xirr = $this->transactionParser->overview->calculateXIRR($this->transactionParser->overview->transactions);

        // echo 'Tot. avgifter: ' . $this->presenter->colorPicker($this->transactionParser->overview->totalFee) . ' SEK' . PHP_EOL;
        // echo 'Tot. utdelningar: ' . $this->presenter->colorPicker($this->transactionParser->overview->totalDividend) . ' SEK' . PHP_EOL;
        // echo 'Tot. köpbelopp: ' . $this->presenter->colorPicker($this->transactionParser->overview->totalBuyAmount) . ' SEK' . PHP_EOL;
        // echo 'Tot. säljbelopp: ' . $this->presenter->colorPicker($this->transactionParser->overview->totalSellAmount) . ' SEK' . PHP_EOL;
        echo 'Tot. nuvarande innehav: ' . $this->presenter->colorPicker($this->transactionParser->overview->totalCurrentHoldings) . ' SEK' . PHP_EOL;
        echo 'Tot. avkastning: ' . $this->presenter->colorPicker($this->transactionParser->overview->totalProfitInclFees) . ' SEK' . PHP_EOL;
        echo 'XIRR: ' . $this->presenter->colorPicker($xirr * 100) . '%' . PHP_EOL;
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
            'bank' => $this->bank,
            'isin' => $this->isin,
            'asset' => $this->asset,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo
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

                $this->transactionParser->overview->addFinalCompanyTransaction($summary->isin, $currentValueOfShares);
            }

            $calculatedReturns = $this->calculateReturnsOnAsset($summary, $currentValueOfShares);

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

    public function calculateReturnsOnAsset(TransactionSummary $summary, ?float $currentValueOfShares): ?stdClass
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

        $this->transactionParser->overview->totalProfitInclFees += $totalReturnInclFees;

        return $result;
    }
}
