<?php

namespace src\View;

use InvalidArgumentException;

class TextColorizer
{
    /** @var array<string, string> */
    private static array $colors = [
        'grey' => "\033[38;5;245m",
        'pink' => "\033[38;5;213m",
        'green' => "\033[32m",
        'red' => "\033[31m",
        'blue' => "\033[1;34m",
        'cyan' => "\033[36m",
        'yellow' => "\033[33m",
        'black' => "\033[30m",
    ];

    /** @var array<string, string> */
    private static array $backgrounds = [
        'green' => "\033[42m",
        'red' => "\033[41m",
        'white' => "\033[47m",
        'black' => "\033[40m",
        'yellow' => "\033[43m",
        'blue' => "\033[44m",
    ];

    public static function colorText(string|float $text, string $color): string
    {
        if (!isset(self::$colors[$color])) {
            throw new InvalidArgumentException("Color '{$color}' is not defined.");
        }
        return self::$colors[$color] . $text . "\033[0m";
    }

    public static function backgroundColor(string $text, string $color): string
    {
        if (!isset(self::$backgrounds[$color])) {
            throw new InvalidArgumentException("Background color '{$color}' is not defined.");
        }
        return self::$backgrounds[$color] . static::colorText($text, 'black') . "\033[0m"; // Using black text by default
    }
}
