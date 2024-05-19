<?php

namespace Src\Libs;

use src\Libs\Command\CalculateProfit;
use src\Libs\Presenter;

class CommandProcessor
{
    private const COMMANDS = [
        'calculate-profit' => [
            'description' => 'A fitting description for the command',
            'options' => [
                'generateCsv' => [
                    'description' => 'Generate CSV file'
                ],
                'date-from' => [
                    'description' => 'Date to calculate profit from',
                ],
                'date-to' => [
                    'description' => 'Date to calculate profit to',
                ],
                'bank' => [
                    'description' => 'Bank to calculate profit for'
                ],
                'isin' => [
                    'description' => 'ISIN to calculate profit for',
                ],
                'asset' => [
                    'description' => 'Asset (name) to calculate profit for'
                ]
            ]
        ],
        'help' => [
            'description' => 'Prints available commands and their options'
        ],
    ];

    private Presenter $presenter;

    public function __construct()
    {
        $this->presenter = new Presenter();
    }

    public function main(array $argv): void
    {
        if (empty($argv)) {
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

        if (!array_key_exists($command, static::COMMANDS)) {
            $this->unknownCommand($command);
            $this->printAvailableCommands();
            exit(1);
        }

        if (isset(static::COMMANDS[$command]['options'])) {
            $availableOptions = static::COMMANDS[$command]['options'];

            foreach ($options as $option => $value) {
                if (!array_key_exists($option, $availableOptions)) {
                    echo $this->presenter->redText("Unknown option: $option\n\n");
                    $this->printAvailableCommands($command);
                    exit(1);
                }
            }
        }

        switch ($command) {
            case 'calculate-profit':
                (new CalculateProfit($options))->execute();
                break;
            case 'help':
                $this->printAvailableCommands();
                break;
            default:
                $this->unknownCommand($command);
                $this->printAvailableCommands();
                break;
        }
    }

    protected function unknownCommand(string $command): void
    {
        echo $this->presenter->redText("Unknown command: $command\n\n");
    }

    protected function printAvailableCommands(?string $command = null): void
    {
        if ($command && array_key_exists($command, self::COMMANDS)) {
            echo $this->presenter->cyanText("Command: $command\n");
            echo $this->presenter->cyanText("Description: " . self::COMMANDS[$command]['description'] . "\n");
            echo $this->presenter->cyanText("Options:\n");

            foreach (self::COMMANDS[$command]['options'] as $option => $details) {
                echo $this->presenter->blueText("  --$option\n");
                echo $this->presenter->blueText("    Description: " . $details['description'] . "\n");
            }
        } else {
            echo $this->presenter->cyanText("Available commands:\n\n");
            foreach (self::COMMANDS as $command => $commandDetails) {
                echo $this->presenter->cyanText("Command: $command\n");
                echo $this->presenter->cyanText("Description: " . $commandDetails['description'] . "\n");

                if (isset($commandDetails['options'])) {
                    echo $this->presenter->cyanText("Options:\n");

                    foreach ($commandDetails['options'] as $option => $details) {
                        echo $this->presenter->blueText("  --$option\n");
                        echo $this->presenter->blueText("    Description: " . $details['description'] . "\n");
                    }
                }
    
                echo "\n";
            }
        }
    }
}
