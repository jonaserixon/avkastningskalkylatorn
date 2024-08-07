<?php

declare(strict_types=1);

namespace Avk\View;

class Logger
{
    private static ?Logger $instance = null;

    /** @var string[] */
    private array $notices = [];

    /** @var string[] */
    private array $warnings = [];

    /** @var string[] */
    private array $infos = [];

    private function __construct()
    {
    }

    public static function getInstance(): Logger
    {
        if (self::$instance === null) {
            self::$instance = new Logger();
        }

        return self::$instance;
    }

    public function addNotice(string $message): void
    {
        $this->notices[] = $message;
    }

    public function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function addInfo(string $message): void
    {
        $this->infos[] = $message;
    }

    /**
     * @return string[]
     */
    public function getNotices(): array
    {
        // sort this
        sort($this->notices);

        return $this->notices;
    }

    /**
     * @return string[]
     */
    public function getWarnings(): array
    {
        sort($this->warnings);

        return $this->warnings;
    }

    /**
     * @return string[]
     */
    public function getInfos(): array
    {
        sort($this->infos);

        return $this->infos;
    }

    public function clear(): void
    {
        $this->notices = [];
        $this->warnings = [];
        $this->infos = [];
    }

    public function printInfos(): Logger
    {
        if (!empty($this->infos)) {
            sort($this->infos);

            echo "\n" . TextColorizer::backgroundColor('Information:', 'blue') . "\n";
            echo implode("\n", $this->infos) . "\n";
        }

        return $this;
    }

    public function printNotices(): Logger
    {
        if (!empty($this->notices)) {
            sort($this->notices);

            echo "\n" . TextColorizer::backgroundColor('Notices:', 'yellow') . "\n";
            echo implode("\n", $this->notices) . "\n";
        }

        return $this;
    }

    public function printWarnings(): Logger
    {
        if (!empty($this->warnings)) {
            sort($this->warnings);

            echo "\n" . TextColorizer::backgroundColor('Warnings:', 'red') . "\n";
            echo implode("\n", $this->warnings) . "\n";
        }

        return $this;
    }

    public function printMessage(string $message): void
    {
        echo $message . PHP_EOL;
    }
}
