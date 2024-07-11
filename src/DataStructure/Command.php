<?php declare(strict_types=1);

namespace Avk\DataStructure;

use Avk\Enum\CommandOptionName;
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

    public function getOption(CommandOptionName $name): CommandOption
    {
        $option = $this->options[$name->value] ?? null;
        if ($option === null) {
            throw new Exception("Option $name->value not found");
        }

        return $option;
    }
}
