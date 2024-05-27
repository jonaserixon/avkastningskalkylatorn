<?php

namespace src\Libs\Command;

use src\Libs\Presenter;

class CommandProcessor
{
    protected Presenter $presenter;

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
        echo "\n=============\n" . $this->presenter->greenText("Execution time: $executionTime seconds\n");
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
                echo $this->presenter->redText("Unknown option: $option\n\n");
                $this->printAvailableCommands($command);
                exit(1);
            } else {
                $requiresValue = $availableOptions[$option]['require-value'];
                if ($requiresValue && $value === true) {
                    echo $this->presenter->redText("Option '$option' requires a value\n\n");
                    $this->printAvailableCommands($command);
                    exit(1);
                }
            }
        }
    }

    protected function unknownCommand(string $command): void
    {
        echo $this->presenter->redText("Unknown command: $command\n\n");
    }

    protected function printAvailableCommands(?string $command = null): void
    {
        if ($command && array_key_exists($command, $this->commands)) {
            echo $this->presenter->cyanText("Command: ") .  $command . "\n";
            echo $this->commands[$command]['description'] . "\n";
            echo $this->presenter->cyanText("Options:\n");

            foreach ($this->commands[$command]['options'] as $option => $details) {
                echo $this->presenter->blueText("  --$option\n");
                echo "    " . $details['description'] . "\n";
            }
        } else {
            echo $this->presenter->pinkText("Available commands:\n\n");
            foreach ($this->commands as $command => $commandDetails) {
                echo $this->presenter->cyanText("Command: ") .  $command . "\n";
                echo $commandDetails['description'] . "\n";

                if (isset($commandDetails['options'])) {
                    echo $this->presenter->cyanText("Options:\n");

                    foreach ($commandDetails['options'] as $option => $details) {
                        echo $this->presenter->blueText("  --$option\n");
                        echo "    " . $details['description'] . "\n";
                    }
                }

                echo "\n";
            }
        }
    }
}
