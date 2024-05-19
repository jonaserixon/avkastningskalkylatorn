<?php

namespace Src\Libs;

use src\Libs\Command\CalculateProfit;
use Src\Libs\Command\Transaction;
use src\Libs\Presenter;

class CommandProcessor
{
    protected const COMMANDS = [
        'help' => [
            'description' => 'Prints available commands and their options'
        ],
        'calculate-profit' => [
            'description' => 'Calculate profits',
            'options' => [
                'export-csv' => [
                    'description' => 'Generate and export CSV file',
                    'default' => false,
                    'require-value' => false
                ],
                'bank' => [
                    'description' => 'Bank to calculate profit for',
                    'require-value' => true
                ],
                'date-from' => [
                    'description' => 'Date to calculate profit from',
                    'require-value' => true
                ],
                'date-to' => [
                    'description' => 'Date to calculate profit to',
                    'require-value' => true
                ],
                'asset' => [
                    'description' => 'Asset (name) to calculate profit for',
                    'require-value' => true
                ],
                'isin' => [
                    'description' => 'ISIN to calculate profit for',
                    'require-value' => true
                ],
                'current-holdings' => [
                    'description' => 'Only calculate profit for current holdings',
                    'default' => false,
                    'require-value' => false
                ],
                'verbose' => [
                    'description' => 'More detailed output',
                    'default' => false,
                    'require-value' => false
                ]
            ]
        ],
        'transaction' => [
            'description' => 'View transaction(s)',
            'options' => [
                'bank' => [
                    'description' => 'Bank to add transaction to',
                    'require-value' => true
                ],
                'date-from' => [
                    'description' => 'Date to calculate profit from',
                    'require-value' => true
                ],
                'date-to' => [
                    'description' => 'Date to calculate profit to',
                    'require-value' => true
                ],
                'type' => [
                    'description' => 'Type of transaction',
                    'require-value' => true
                ],
                'isin' => [
                    'description' => 'ISIN of asset',
                    'require-value' => true
                ],
                'asset' => [
                    'description' => 'Name of asset',
                    'require-value' => true
                ],
                'fee' => [
                    'description' => 'Fee of transaction',
                    'require-value' => false
                ]
            ]
        ]
    ];

    private Presenter $presenter;

    public function __construct()
    {
        $this->presenter = new Presenter();
    }

    public function main(array $argv): void
    {
        if (!isset($argv[0]) || !isset($argv[1])) {
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

        $this->validateOptions($command, $options);

        switch ($command) {
            case 'help':
                $this->printAvailableCommands();
                break;
            case 'calculate-profit':
                (new CalculateProfit($options))->execute();
                break;
            case 'transaction':
                (new Transaction($options))->execute();
                break;
            default:
                $this->unknownCommand($command);
                $this->printAvailableCommands();
                break;
        }
    }

    protected function validateOptions(string $command, array $options): void
    {
        if (!isset(static::COMMANDS[$command]['options'])) {
            return;
        }

        $availableOptions = static::COMMANDS[$command]['options'];
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
        if ($command && array_key_exists($command, self::COMMANDS)) {
            echo $this->presenter->cyanText("Command: ") .  $command . "\n";
            echo $this->presenter->cyanText("Description: ") . self::COMMANDS[$command]['description'] . "\n";
            echo $this->presenter->cyanText("Options:\n");

            foreach (self::COMMANDS[$command]['options'] as $option => $details) {
                echo $this->presenter->blueText("  --$option\n");
                echo "    Description: " . $details['description'] . "\n";
            }
        } else {
            echo $this->presenter->pinkText("Available commands:\n\n");
            foreach (self::COMMANDS as $command => $commandDetails) {
                echo $this->presenter->cyanText("Command: ") .  $command . "\n";
                echo $this->presenter->cyanText("Description: ") . $commandDetails['description'] . "\n";

                if (isset($commandDetails['options'])) {
                    echo $this->presenter->cyanText("Options:\n");

                    foreach ($commandDetails['options'] as $option => $details) {
                        echo $this->presenter->blueText("  --$option\n");
                        echo "    Description: " . $details['description'] . "\n";
                    }
                }

                echo "\n";
            }
        }
    }
}
