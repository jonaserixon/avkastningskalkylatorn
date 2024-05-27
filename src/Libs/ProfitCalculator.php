<?php

namespace src\Libs;

use src\DataStructure\AssetReturn;
use src\DataStructure\FinancialAsset;
use src\DataStructure\FinancialOverview;
use src\Libs\FileManager\Importer\StockPrice;
use stdClass;

class ProfitCalculator
{
    private bool $filterCurrentHoldings;
    private StockPrice $stockPrice;

    public function __construct(bool $currentHoldings)
    {
        $this->filterCurrentHoldings = $currentHoldings;
        $this->stockPrice = new StockPrice();
    }

    /**
     * @param FinancialAsset[] $assets
     * @param FinancialOverview $overview
     *
     * @return stdClass
     */
    public function calculate(array $assets, FinancialOverview $overview): stdClass
    {
        $currentHoldingsMissingPricePerShare = [];
        $filteredAssets = [];
        foreach ($assets as $asset) {
            if ($this->filterCurrentHoldings && (int) $asset->getCurrentNumberOfShares() <= 0) {
                continue;
            }

            if (!empty($asset->isin)) {
                $currentPricePerShare = $this->stockPrice->getCurrentPriceByIsin($asset->isin);

                if ($currentPricePerShare && (int) $asset->getCurrentNumberOfShares() > 0) {
                    // $asset->name = $this->stockPrice->getNameByIsin($asset->isin);
                    $currentValueOfShares = $asset->getCurrentNumberOfShares() * $currentPricePerShare;

                    $overview->totalCurrentHoldings += $currentValueOfShares;
                    $overview->addCashFlow(
                        date('Y-m-d'),
                        $currentValueOfShares,
                        $asset->name,
                        'current_holding_value',
                        '',
                        ''
                    );
                    $overview->lastTransactionDate = date('Y-m-d');

                    $asset->setCurrentPricePerShare($currentPricePerShare);
                    $asset->setCurrentValueOfShares($currentValueOfShares);
                    $asset->assetReturn = $this->calculateTotalReturnForAsset($asset);

                    $asset->unrealizedGainLoss = $asset->getCurrentValueOfShares() - $asset->costBasis;

                    $filteredAssets[] = $asset;

                    continue;
                }

                $asset->assetReturn = $this->calculateTotalReturnForAsset($asset);

                $isMissingPricePerShare = (int) $asset->getCurrentNumberOfShares() > 0 && !$currentPricePerShare;

                if ($isMissingPricePerShare) {
                    $currentHoldingsMissingPricePerShare[] = $asset->name . ' (' . $asset->isin . ')';
                }
            }
        }

        // Important for calculations etc.
        usort($overview->cashFlows, function ($a, $b) {
            return strtotime($a->date) <=> strtotime($b->date);
        });

        $result = new stdClass();
        $result->currentHoldingsMissingPricePerShare = $currentHoldingsMissingPricePerShare;

        if ($this->filterCurrentHoldings) {
            $result->assets = $filteredAssets;
        } else {
            $result->assets = $assets;
        }

        $overview->returns = $this->calculateTotalReturnForFinancialOverview($overview);

        $result->overview = $overview;
        $this->calculateCurrentHoldingsWeighting($result->overview, $result->assets);

        return $result;
    }

    /**
     * Calculate the current holdings weighting for each asset.
     * @param FinancialOverview $overview
     * @param FinancialAsset[] $assets
     */
    protected function calculateCurrentHoldingsWeighting(FinancialOverview $overview, array $assets): void
    {
        foreach ($assets as $asset) {
            if ($asset->getCurrentValueOfShares() > 0) {
                $weighting = $asset->getCurrentValueOfShares() / $overview->totalCurrentHoldings * 100;
                $overview->currentHoldingsWeighting[$asset->name] = round($weighting, 4);
            }
        }
    }

    protected function calculateTotalReturnForAsset(FinancialAsset $asset): ?AssetReturn
    {
        if ($asset->getCurrentValueOfShares() === null) {
            $asset->setCurrentValueOfShares(0);
        }

        $totalReturnInclFees = 0;
        $totalReturnInclFees += $asset->getBuyAmount();
        $totalReturnInclFees += $asset->getSellAmount();
        $totalReturnInclFees += $asset->getDividendAmount();
        $totalReturnInclFees += $asset->getFeeAmount();
        $totalReturnInclFees += $asset->getCurrentValueOfShares();

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
        $totalReturnInclFees += $overview->totalTax;
        $totalReturnInclFees += $overview->totalInterest;
        $totalReturnInclFees += $overview->totalForeignWithholdingTax;
        $totalReturnInclFees += $overview->totalReturnedForeignWithholdingTax;

        $result = new AssetReturn();
        $result->totalReturnInclFees = $totalReturnInclFees;

        return $result;
    }
}
