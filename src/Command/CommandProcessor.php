<?php

namespace src\Command;

use src\View\Logger;
use src\View\Presenter;
use src\View\TextColorizer;

class CommandProcessor
{
    protected Presenter $presenter;
    protected Logger $logger;

    /** @var mixed[] */
    protected array $commands;

    public function __construct()
    {
        $this->presenter = new Presenter();
        $this->commands = CommandDefinitions::COMMANDS;
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

        $command = $argv[1];

        $remainingArgs = array_slice($argv, 2);

        $options = [];
        foreach ($remainingArgs as $arg) {
            if (strpos($arg, '--') === 0) {
                $option = explode('=', substr($arg, 2), 2);
                $options[$option[0]] = $option[1] ?? true;
            }
        }

        if (!array_key_exists($command, $this->commands)) {
            $this->unknownCommand($command);
            $this->printAvailableCommands();
            exit(1);
        }

        $this->validateOptions($command, $options);

        $startTime = microtime(true);

        switch ($command) {
            case 'help':
                $this->printAvailableCommands();
                break;
            case 'calculate':
                (new CalculateProfitCommand($options))->execute();
                break;
            case 'generate-isin-list':
                (new GenerateIsinListCommand($options))->execute();
                break;
            case 'transaction':
                (new TransactionCommand($options))->execute();
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
        $availableOptions = $this->commands[$command]['options'] ?? null;
        if ($availableOptions === null) {
            return;
        }

        foreach ($options as $option => $value) {
            if (!array_key_exists($option, $availableOptions)) {
                echo TextColorizer::colorText("Unknown option: $option\n\n", 'red');
                $this->printAvailableCommands($command);
                exit(1);
            } else {
                $requiresValue = $availableOptions[$option]['require-value'];
                if ($requiresValue && $value === true) {
                    echo TextColorizer::colorText("Option '$option' requires a value\n\n", 'red');
                    $this->printAvailableCommands($command);
                    exit(1);
                }
            }
        }
    }

    protected function unknownCommand(string $command): void
    {
        echo TextColorizer::colorText("Unknown command: $command\n\n", 'red');
    }

    protected function printAvailableCommands(?string $command = null): void
    {
        if ($command && array_key_exists($command, $this->commands)) {
            echo TextColorizer::colorText("Command: ", 'cyan') .  $command . "\n";
            echo $this->commands[$command]['description'] . "\n";
            echo TextColorizer::colorText("Options:\n", 'cyan');

            foreach ($this->commands[$command]['options'] as $option => $details) {
                echo TextColorizer::colorText("  --$option\n", 'blue');
                echo "    " . $details['description'] . "\n";
            }
        } else {
            echo TextColorizer::colorText("Available commands:\n\n", 'pink');
            foreach ($this->commands as $command => $commandDetails) {
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
}
