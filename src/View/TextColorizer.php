<?php

namespace src\View;

use InvalidArgumentException;

class TextColorizer
{
    // TODO: skapa konstanter för färger och bakgrunder

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
        'pink' => "\033[45m",
        'grey' => "\033[47m",
    ];

    public static function colorText(string|float $text, string $color): string
    {
        if (!isset(self::$colors[$color])) {
            throw new InvalidArgumentException("Color '{$color}' is not defined.");
        }

        return self::$colors[$color] . $text . "\033[0m";
    }

    public static function backgroundColor(string $text, string $backgroundColor = 'white', string $textColor = 'black'): string
    {
        if (!isset(self::$backgrounds[$backgroundColor])) {
            throw new InvalidArgumentException("Background color '{$backgroundColor}' is not defined.");
        }
        if (!isset(self::$colors[$textColor])) {
            throw new InvalidArgumentException("Text color '{$textColor}' is not defined.");
        }

        return self::$backgrounds[$backgroundColor] . static::colorText($text, $textColor) . "\033[0m";
    }
}
