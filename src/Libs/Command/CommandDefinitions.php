<?php

namespace src\Libs\Command;

class CommandDefinitions
{
    public const COMMANDS = [
        'help' => [
            'description' => 'Prints available commands and their options'
        ],
        'calculate' => [
            'description' => 'Calculate profits',
            'options' => [
                'export-csv' => [
                    'description' => 'Generate and export CSV file',
                    'default' => false,
                    'require-value' => false
                ],
                'bank' => [
                    'description' => 'Bank(s) to calculate profit for',
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
                    'description' => 'Asset(s) (name) to calculate profit for',
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
                ],
                'overview' => [
                    'description' => 'Show investment overview report',
                    'default' => false,
                    'require-value' => false
                ]
            ]
        ],
        'generate-isin-list' => [
            'description' => 'Generate a list of ISINs in csv format',
            'options' => [
                'bank' => [
                    'description' => 'Bank(s) to generate ISIN list for',
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
                'current-holdings' => [
                    'description' => 'Only calculate profit for current holdings',
                    'default' => false,
                    'require-value' => false
                ],
                'fee' => [
                    'description' => 'Fee of transaction',
                    'require-value' => false
                ],
                'cash-flow' => [
                    'description' => 'Cash flow of transactions',
                    'require-value' => false
                ],
            ]
        ]
    ];
}
