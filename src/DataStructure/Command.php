<?php

namespace Avk\DataStructure;

use Exception;

readonly class Command
{
    public string $name;

    /** @var CommandOption[] */
    public array $options;

    /**
     * @param CommandOption[] $options
     */
    public function __construct(string $name, array $options)
    {
        $this->name = $name;
        $this->options = $options;
    }

    public function getOption(string $name): CommandOption
    {
        $option = $this->options[$name] ?? null;
        if ($option === null) {
            throw new Exception("Option $name not found");
        }

        return $option;
    }
}
