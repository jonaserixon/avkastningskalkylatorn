<?php declare(strict_types=1);

namespace Avk\DataStructure;

class AssetPerformance
{
    public float $absolutePerformance = 0;
    public ?float $xirr = null;
    // TODO: add TWR
    public float $realizedGainLoss = 0;
    public float $unrealizedGainLoss = 0;
    public float $costBasis = 0;
}
