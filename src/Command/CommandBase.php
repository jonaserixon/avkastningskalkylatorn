<?php

namespace Avk\Command;

use Avk\DataStructure\Command;
use Avk\View\Presenter;

abstract class CommandBase
{
    protected Command $command;
    protected Presenter $presenter;

    public function __construct(Command $command, Presenter $presenter)
    {
        $this->command = $command;
        $this->presenter = $presenter;
    }

    abstract public function execute(): void;
}
