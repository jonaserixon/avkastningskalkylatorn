<?php

namespace src\Libs;

use DateTime;
use Exception;
use src\DataStructure\AssetReturn;
// use src\Libs\FileManager\Exporter;
use src\DataStructure\Overview;
use src\DataStructure\TransactionSummary;
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

    private TransactionParser $transactionParser;
    private StockPrice $stockPrice;

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

        $this->transactionParser = new TransactionParser();
        $this->stockPrice = new StockPrice();
    }

    public function calculate(): stdClass
    {
        $summaries = $this->transactionParser->getTransactionsOverview($this->getTransactions());

        $currentHoldingsMissingPricePerShare = [];
        $filteredSummaries = [];
        foreach ($summaries as $summary) {
            if ($this->filterCurrentHoldings && (int) $summary->currentNumberOfShares <= 0) {
                continue;
            }

            $currentPricePerShare = $this->stockPrice->getCurrentPriceByIsin($summary->isin);

            if ($currentPricePerShare) {
                $currentValueOfShares = $summary->currentNumberOfShares * $currentPricePerShare;

                $this->transactionParser->overview->totalCurrentHoldings += $currentValueOfShares;
                $this->transactionParser->overview->addFinalAssetTransaction($summary->isin, $currentValueOfShares);
                $this->transactionParser->overview->addFinalTransaction($currentValueOfShares);

                $summary->currentPricePerShare = $currentPricePerShare;
                $summary->currentValueOfShares = $currentValueOfShares;
                $summary->assetReturn = $this->calculateTotalReturnForSummary($summary);

                $filteredSummaries[] = $summary;
                continue;
            }

            $isMissingPricePerShare = (int) $summary->currentNumberOfShares > 0 && !$currentPricePerShare;
            if ($isMissingPricePerShare) {
                $currentHoldingsMissingPricePerShare[] = $summary->name . ' (' . $summary->isin . ')';
            }

            $summary->assetReturn = $this->calculateTotalReturnForSummary($summary);
        }

        /*
        if ($this->exportCsv) {
            Exporter::generateCsvExport($filteredSummaries, $stockPrice);
            Exporter::testGenerateCsvExport($this->transactionParser->overview->transactions);
        }
        */

        $result = new stdClass();
        $result->currentHoldingsMissingPricePerShare = $currentHoldingsMissingPricePerShare;

        if ($this->filterCurrentHoldings) {
            $result->summaries = $filteredSummaries;
        } else {
            $result->summaries = $summaries;
        }

        $result->overview = $this->transactionParser->overview;
        $result->overview->returns = $this->calculateTotalReturnForOverview($result->overview);
        $this->calculateCurrentHoldingsWeighting($result->overview, $result->summaries);
        $result->xirr = $this->calculateXIRR($this->transactionParser->overview->transactions);

        return $result;
    }

    protected function calculateCurrentHoldingsWeighting(Overview $overview, array $summaries): void
    {
        foreach ($summaries as $summary) {
            if ($summary->currentValueOfShares > 0) {
                $weighting = $summary->currentValueOfShares / $overview->totalCurrentHoldings * 100;
                // $overview->currentHoldingsWeighting[$summary->name] = round($weighting, 2);
                $overview->currentHoldingsWeighting[$summary->isin] = round($weighting, 2);
            }
        }
    }

    protected function calculateTotalReturnForSummary(TransactionSummary $summary): ?AssetReturn
    {
        if ($summary->currentValueOfShares === null) {
            $summary->currentValueOfShares = 0;
        }

        if ($summary->buyAmountTotal <= 0) {
            return null;
        }

        // Beräkna total avkastning exklusive avgifter
        $totalReturnExclFees = $summary->sellAmountTotal + $summary->dividendAmountTotal + $summary->currentValueOfShares - $summary->buyAmountTotal;

        $totalReturnExclFeesPercent = round($totalReturnExclFees / $summary->buyAmountTotal * 100, 2);

        // Beräkna total avkastning inklusive avgifter
        $adjustedTotalBuyAmount = $summary->buyAmountTotal + $summary->feeBuyAmountTotal;
        $adjustedTotalSellAmount = $summary->sellAmountTotal + $summary->dividendAmountTotal - $summary->feeSellAmountTotal;

        $totalReturnInclFees = $adjustedTotalSellAmount + $summary->currentValueOfShares - $adjustedTotalBuyAmount;
        $totalReturnInclFeesPercent = round($totalReturnInclFees / $adjustedTotalBuyAmount * 100, 2);

        $result = new AssetReturn();
        $result->totalReturnExclFees = $totalReturnExclFees;
        $result->totalReturnExclFeesPercent = $totalReturnExclFeesPercent;
        $result->totalReturnInclFees = $totalReturnInclFees;
        $result->totalReturnInclFeesPercent = $totalReturnInclFeesPercent;

        $this->transactionParser->overview->totalProfitInclFees += $totalReturnInclFees; // TODO: move this line

        return $result;
    }

    protected function calculateTotalReturnForOverview(Overview $overview): AssetReturn
    {
        // Beräkna total avkastning exklusive avgifter
        $totalReturnExclFees = $overview->totalSellAmount + $overview->totalDividend + $overview->totalCurrentHoldings - $overview->totalBuyAmount;

        $totalReturnExclFeesPercent = round($totalReturnExclFees / $overview->totalBuyAmount * 100, 2);

        // Beräkna total avkastning inklusive avgifter
        $adjustedTotalBuyAmount = $overview->totalBuyAmount + $overview->totalBuyFee;
        $adjustedTotalSellAmount = $overview->totalSellAmount + $overview->totalDividend - $overview->totalSellFee;

        $totalReturnInclFees = $adjustedTotalSellAmount + $overview->totalCurrentHoldings - $adjustedTotalBuyAmount;
        $totalReturnInclFeesPercent = round($totalReturnInclFees / $adjustedTotalBuyAmount * 100, 2);

        $result = new AssetReturn();
        $result->totalReturnExclFees = $totalReturnExclFees;
        $result->totalReturnExclFeesPercent = $totalReturnExclFeesPercent;
        $result->totalReturnInclFees = $totalReturnInclFees;
        $result->totalReturnInclFeesPercent = $totalReturnInclFeesPercent;

        return $result;
    }

    protected function calculateXIRR(array $transactions)
    {
        $minDate = $transactions[0]->date;
        $minDate = new DateTime($minDate);

        // NPV (Net Present Value) function
        $npv = function ($rate) use ($transactions, $minDate) {
            $sum = 0;
            foreach ($transactions as $transaction) {
                $amount = $transaction->amount;
                $date = new DateTime($transaction->date);
                $days = $minDate->diff($date)->days;
                $sum += $amount / pow(1 + $rate, $days / 365);
            }
            return $sum;
        };

        // Newton-Raphson method to find the root
        $guess = 0.1;
        $tolerance = 0.0001;
        $maxIterations = 100;
        $iteration = 0;

        while ($iteration < $maxIterations) {
            $npvValue = $npv($guess);
            $npvDerivative = ($npv($guess + $tolerance) - $npvValue) / $tolerance;

            // Hantera liten derivata
            if (abs($npvDerivative) < $tolerance) {
                // Justera gissningen lite för att undvika division med noll
                $npvDerivative = $tolerance;
            }

            $newGuess = $guess - $npvValue / $npvDerivative;

            if (abs($newGuess - $guess) < $tolerance) {
                return $newGuess;
            }

            $guess = $newGuess;
            $iteration++;
        }

        throw new Exception("XIRR did not converge");
    }

    public function calculateCAGR()
    {
        // to be implemented(?)
    }

    /**
     * Returns a list of sorted and possibly filtered transactions.
     */
    public function getTransactions(): array // TODO: should be moved somewhere not related to profits
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
            'dateTo' => $this->filterDateTo,
            'currentHoldings' => $this->filterCurrentHoldings
        ];

        foreach ($filters as $key => $value) {
            if ($value) {
                $value = mb_strtoupper($value);
                $transactions = array_filter($transactions, function ($transaction) use ($key, $value) {
                    if ($key === 'asset') {
                        $assets = explode(',', $value);
                        foreach ($assets as $asset) {
                            if (str_contains(mb_strtoupper($transaction->name), trim($asset))) {
                                return true;
                            }
                        }

                        return false;
                    }

                    if ($key === 'dateFrom') {
                        return strtotime($transaction->date) >= strtotime($value);
                    }

                    if ($key === 'dateTo') {
                        return strtotime($transaction->date) <= strtotime($value);
                    }

                    if ($key === 'currentHoldings') {
                        $currentPricePerShare = $this->stockPrice->getCurrentPriceByIsin($transaction->isin);
                        return $currentPricePerShare !== null;
                    }

                    return mb_strtoupper($transaction->{$key}) === $value;
                });
            }
        }

        if (empty($transactions)) {
            throw new Exception('No transactions found');
        }

        // Sort transactions by date, bank and ISIN. (important for calculations and handling of transactions)
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
}
