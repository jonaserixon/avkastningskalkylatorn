<?php

declare(strict_types=1);

namespace Avk\Command;

use Avk\DataStructure\Command;
use Avk\DataStructure\CommandOption;
use Avk\Enum\CommandOptionName;
use Avk\View\Presenter;
use Avk\View\TextColorizer;

class CommandProcessor
{
    protected Presenter $presenter;

    /** @var mixed[] */
    protected array $commands;

    public function __construct()
    {
        $this->presenter = new Presenter();
        $this->commands = yaml_parse_file(ROOT_PATH . '/config/commands.yaml');
    }

    /**
     * @param mixed[] $argv
     */
    public function main(array $argv): void
    {
        if (count($argv) < 2) {
            $this->printAvailableCommands();
            exit(1);
        }

        $commandName = $argv[1];

        $remainingArgs = array_slice($argv, 2);

        $options = [];
        foreach ($remainingArgs as $arg) {
            if (strpos($arg, '--') === 0) {
                $option = explode('=', substr($arg, 2), 2);
                $options[$option[0]] = $option[1] ?? true;
            }
        }

        $command = $this->getCommand($commandName, $options);

        $this->validateOptions($command->name, $options);

        $startTime = microtime(true);

        switch ($command->name) {
            case 'help':
                $this->printAvailableCommands();
                break;
            case 'calculate':
                (new CalculateProfitCommand($command, $this->presenter))->execute();
                break;
            case 'generate-isin-list':
                (new GenerateIsinListCommand($command, $this->presenter))->execute();
                break;
            case 'generate-ticker-list':
                (new GenerateTickerListCommand($command, $this->presenter))->execute();
                break;
            case 'transaction':
                (new TransactionCommand($command, $this->presenter))->execute();
                break;
            case 'pp-export':
                (new PortfolioPerformanceExportCommand($command, $this->presenter))->execute();
                break;
            default:
                $this->unknownCommand($command);
                $this->printAvailableCommands();
                break;
        }

        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 5);
        echo "\n=============\n" . TextColorizer::colorText("Execution time: $executionTime seconds\n", 'green');
    }

    /**
     * @param string $command
     * @param mixed[] $options
     */
    protected function validateOptions(string $command, array $options): void
    {
        $availableOptions = $this->commands['commands'][$command]['options'] ?? null;
        if ($availableOptions === null) {
            return;
        }

        foreach ($options as $option => $value) {
            if (!array_key_exists($option, $availableOptions)) {
                echo TextColorizer::colorText("Unknown option: $option\n\n", 'red');
                $this->printAvailableCommands($command);
                exit(1);
            } else {
                $requiresValue = $availableOptions[$option]['require-value'] ?? false;
                if ($requiresValue && $value === true) {
                    echo TextColorizer::colorText("Option '$option' requires a value\n\n", 'red');
                    $this->printAvailableCommands($command);
                    exit(1);
                }
            }
        }
    }

    protected function unknownCommand(mixed $command): void
    {
        echo TextColorizer::colorText("Unknown command: $command\n\n", 'red');
    }

    protected function printAvailableCommands(?string $command = null): void
    {
        if ($command && array_key_exists($command, $this->commands['commands'])) {
            echo TextColorizer::colorText("Command: ", 'cyan') .  $command . "\n";
            echo $this->commands['commands'][$command]['description'] . "\n";
            echo TextColorizer::colorText("Options:\n", 'cyan');

            foreach ($this->commands['commands'][$command]['options'] as $option => $details) {
                echo TextColorizer::colorText("  --$option\n", 'blue');
                echo "    " . $details['description'] . "\n";
            }
        } else {
            echo TextColorizer::colorText("Available commands:\n\n", 'pink');
            foreach ($this->commands['commands'] as $command => $commandDetails) {
                echo TextColorizer::colorText("Command: ", 'cyan') .  $command . "\n";
                echo $commandDetails['description'] . "\n";

                if (isset($commandDetails['options'])) {
                    echo TextColorizer::colorText("Options:\n", 'cyan');

                    foreach ($commandDetails['options'] as $option => $details) {
                        echo TextColorizer::colorText("  --$option\n", 'blue');
                        echo "    " . $details['description'] . "\n";
                    }
                }

                echo "\n";
            }
        }
    }

    /**
     * @param mixed[] $options
     */
    protected function getCommand(string $commandName, array $options): Command
    {
        if (!isset($this->commands['commands'][$commandName])) {
            $this->unknownCommand($commandName);
            $this->printAvailableCommands();
            exit(1);
        }

        $commandData = $this->commands['commands'][$commandName];

        $commandOptions = [];
        if (isset($commandData['options'])) {
            foreach ($commandData['options'] as $name => $commandOption) {
                $optionName = CommandOptionName::from($name);

                if (isset($options[$optionName->value])) {
                    $value = $options[$optionName->value];
                } else {
                    $value = $commandOption['default'] ?? null;
                }

                $commandOptions[$optionName->value] = new CommandOption(
                    $optionName,
                    $value,
                    $commandData['options'][$optionName->value]['default'] ?? null,
                    $commandData['options'][$optionName->value]['require-value'] ?? false
                );
            }
        }

        $command = new Command($commandName, $commandOptions);

        return $command;
    }
}
