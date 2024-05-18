<?php

namespace Src\Libs;

use src\Libs\Command\CalculateProfit;

class CommandProcessor
{
    private const COMMANDS = [
        'calculate-profit' => [
            'description' => 'A fitting description for the command',
            'options' => [
                'generateCsv' => [
                    'description' => 'Generate CSV file',
                    'default' => 'no'
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
        ]
    ];

    public function main(array $argv): void
    {
        if (empty($argv)) {
            echo "Usage: avk <command> [options]\n";
            echo "Available commands: x, y and z\n";
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
            echo "Unknown command: $command\n";
            echo "Available commands: x, y and z\n";
            exit(1);
        }

        if (isset(static::COMMANDS[$command]['options'])) {
            $availableOptions = static::COMMANDS[$command]['options'];

            foreach ($options as $option => $value) {
                if (!array_key_exists($option, $availableOptions)) {
                    echo "Unknown option: $option\n";
                    echo "Available options: x, y and z\n";
                    exit(1);
                }
            }
        }

        switch ($command) {
            case 'calculate-profit':
                (new CalculateProfit($options))->execute();
                break;
            case 'hello':
                if (isset($options['name'])) {
                    echo $options['name']."\n";
                } else {
                    echo "Usage: avk hello [name]\n";
                }
                break;
            default:
                echo "Unknown command: $command\n";
                echo "Available commands: x, y and z\n";
                break;
        }
    }
}
