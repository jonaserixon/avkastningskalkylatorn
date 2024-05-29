<?php

namespace src\Libs;

class Utility
{
    public static function bcabs(float|string $value, int $scale = 15): string
    {
        return bccomp((string) $value, '0', $scale) < 0
            ? bcmul((string) $value, '-1', $scale)
            : (string) $value;
    }

    public static function isNearlyZero(float|string $value, float $tolerance = 1e-12): bool
    {
        $value = static::formatNumberForBCMath(abs($value));
        $tolerance = static::formatNumberForBCMath($tolerance);
        return bccomp($value, $tolerance, 15) < 0;
    }

    public static function formatNumberForBCMath(float $number, int $decimals = 15): string
    {
        return number_format($number, $decimals, '.', ''); // Konverterar till sträng med 15 decimalers precision
    }

    public static function strContains(string $haystack, string $needle): bool
    {
        $haystack = mb_strtolower($haystack);
        $needle = mb_strtolower($needle);

        return str_contains($haystack, $needle);
    }
}
