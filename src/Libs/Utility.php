<?php

namespace src\Libs;

class Utility
{
    public static function bcabs(float|string $value, int $scale = 15): float|string
    {
        return bccomp($value, '0', $scale) < 0 ? bcmul($value, '-1', $scale) : $value;
    }

    public static function isNearlyZero(float|string $value, float $tolerance = 1e-12): bool
    {
        return bccomp(static::formatNumberForBCMath(abs($value)), static::formatNumberForBCMath($tolerance), 15) < 0;
    }

    public static function formatNumberForBCMath(float $number, int $decimals = 15): string
    {
        return number_format($number, $decimals, '.', ''); // Konverterar till sträng med 15 decimalers precision
    }
}
