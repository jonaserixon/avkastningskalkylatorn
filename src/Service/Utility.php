<?php

declare(strict_types=1);

namespace Avk\Service;

use Exception;
use stdClass;

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
        $value = static::formatNumberForBCMath(abs((float) $value));
        $tolerance = static::formatNumberForBCMath($tolerance);
        return bccomp($value, $tolerance, 15) < 0;
    }

    public static function formatNumberForBCMath(float $number, int $decimals = 15): string
    {
        return number_format($number, $decimals, '.', '');
    }

    public static function strContains(string $haystack, string $needle): bool
    {
        $haystack = mb_strtolower($haystack);
        $needle = mb_strtolower($needle);

        return str_contains($haystack, $needle);
    }

    public static function getLatestModifiedFile(string $directory, string $fileType = 'csv'): ?string
    {
        if (!is_dir($directory) || !is_readable($directory)) {
            throw new Exception('Directory does not exist or is not readable.');
        }

        $latestFile = null;
        $latestTime = 0;
        $files = glob($directory . '/*.' . $fileType);
        if ($files === false) {
            throw new Exception('Failed to read directory.');
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                $modificationTime = filemtime($file);
                if ($modificationTime > $latestTime) {
                    $latestTime = $modificationTime;
                    $latestFile = $file;
                }
            }
        }

        return $latestFile;
    }

    /**
     * @return mixed[]|stdClass
     */
    public static function jsonDecodeFromFile(string $filePath): array|stdClass
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new Exception('File does not exist or is not readable.');
        }

        $json = file_get_contents($filePath);
        if ($json === false) {
            throw new Exception('Failed to read file.');
        }

        $data = json_decode($json);
        if ($data === null) {
            throw new Exception('Failed to decode JSON.');
        }

        return $data;
    }
}
