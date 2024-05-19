<?php

namespace Src\Libs\Command;

use Src\Libs\CommandProcessor;

class Transaction extends CommandProcessor
{
    private array $options;

    public function __construct(array $options)
    {
        $this->options = $options;
    }

    public function execute(): void
    {
        echo 'To be implemented...' . PHP_EOL;
    }
}
