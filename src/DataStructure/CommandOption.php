<?php

namespace Avk\DataStructure;

readonly class CommandOption {
    public string $name;
    public mixed $value;
    public ?bool $defaultValue;
    public bool $requireValue;

    public function __construct(string $name, mixed $value, ?bool $defaultValue, bool $requireValue)
    {
        $this->name = $name;
        $this->value = $value;
        $this->defaultValue = $defaultValue;
        $this->requireValue = $requireValue;
    }
}
