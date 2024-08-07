<?php

declare(strict_types=1);

namespace Avk\DataStructure;

use Avk\Enum\CommandOptionName;

readonly class CommandOption
{
    public CommandOptionName $name;
    public mixed $value;
    public ?bool $defaultValue;
    public bool $requireValue;

    public function __construct(CommandOptionName $name, mixed $value, ?bool $defaultValue, bool $requireValue)
    {
        $this->name = $name;
        $this->value = $value;
        $this->defaultValue = $defaultValue;
        $this->requireValue = $requireValue;
    }
}
