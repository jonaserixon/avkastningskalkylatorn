<?php

namespace src\Libs;

use Exception;
use src\DataStructure\AssetReturn;
use src\DataStructure\FinancialOverview;
use src\DataStructure\FinancialAsset;
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
        $assets = $this->transactionParser->getFinancialAssets($this->getTransactions());

        $currentHoldingsMissingPricePerShare = [];
        $filteredAssets = [];
        foreach ($assets as $asset) {
            if ($this->filterCurrentHoldings && (int) $asset->currentNumberOfShares <= 0) {
                continue;
            }

            if (!empty($asset->isin)) {
                $currentPricePerShare = $this->stockPrice->getCurrentPriceByIsin($asset->isin);

                if ($currentPricePerShare && (int) $asset->currentNumberOfShares > 0) {
                    // $asset->name = $this->stockPrice->getNameByIsin($asset->isin);
                    $currentValueOfShares = $asset->currentNumberOfShares * $currentPricePerShare;

                    $this->transactionParser->overview->totalCurrentHoldings += $currentValueOfShares;
                    $this->transactionParser->overview->addCashFlow(date('Y-m-d'), $currentValueOfShares, $asset->name, 'current_holding_value', '', '');

                    $asset->currentPricePerShare = $currentPricePerShare;
                    $asset->currentValueOfShares = $currentValueOfShares;
                    $asset->assetReturn = $this->calculateTotalReturnForAsset($asset);

                    $filteredAssets[] = $asset;

                    continue;
                }

                $asset->assetReturn = $this->calculateTotalReturnForAsset($asset);

                $isMissingPricePerShare = (int) $asset->currentNumberOfShares > 0 && !$currentPricePerShare;

                if ($isMissingPricePerShare) {
                    $currentHoldingsMissingPricePerShare[] = $asset->name . ' (' . $asset->isin . ')';
                }
            }
        }

        // Important for calculations etc.
        usort($this->transactionParser->overview->cashFlows, function ($a, $b) {
            return strtotime($a->date) <=> strtotime($b->date);
        });

        $result = new stdClass();
        $result->currentHoldingsMissingPricePerShare = $currentHoldingsMissingPricePerShare;

        if ($this->filterCurrentHoldings) {
            $result->assets = $filteredAssets;
        } else {
            $result->assets = $assets;
        }

        $result->overview = $this->transactionParser->overview;
        $result->overview->returns = $this->calculateTotalReturnForFinancialOverview($result->overview);
        $this->calculateCurrentHoldingsWeighting($result->overview, $result->assets);

        return $result;
    }

    protected function calculateCurrentHoldingsWeighting(FinancialOverview $overview, array $assets): void
    {
        foreach ($assets as $asset) {
            if ($asset->currentValueOfShares > 0) {
                $weighting = $asset->currentValueOfShares / $overview->totalCurrentHoldings * 100;
                $overview->currentHoldingsWeighting[$asset->name] = round($weighting, 4);
            }
        }
    }

    protected function calculateTotalReturnForAsset(FinancialAsset $asset): ?AssetReturn
    {
        if ($asset->currentValueOfShares === null) {
            $asset->currentValueOfShares = 0;
        }

        $totalReturnInclFees = $asset->buy + $asset->sell + $asset->dividend + $asset->fee + $asset->currentValueOfShares;

        $result = new AssetReturn();
        $result->totalReturnInclFees = $totalReturnInclFees;

        // $this->transactionParser->overview->totalProfitInclFees += $totalReturnInclFees;

        return $result;
    }

    protected function calculateTotalReturnForFinancialOverview(FinancialOverview $overview): AssetReturn
    {
        $totalReturnInclFees = 0;
        $totalReturnInclFees += $overview->totalSellAmount;
        $totalReturnInclFees += $overview->totalDividend;
        $totalReturnInclFees += $overview->totalCurrentHoldings;
        $totalReturnInclFees += $overview->totalBuyAmount;
        $totalReturnInclFees += $overview->totalFee;
        $totalReturnInclFees += $overview->totalForeignWithholdingTax;
        $totalReturnInclFees += $overview->totalReturnedForeignWithholdingTax;

        $result = new AssetReturn();
        $result->totalReturnInclFees = $totalReturnInclFees;

        return $result;
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
                        // To support multiple assets
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
