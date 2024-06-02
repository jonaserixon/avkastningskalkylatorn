<?php

namespace src\Command;

readonly class CommandDefinitions
{
    // TODO: make this into a YAML or something
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
                'account' => [
                    'description' => 'Account(s) to calculate profit for',
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
                ],
                'display-log' => [
                    'description' => 'Display logs related to how transactions are handled and more',
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
                ],
                'account' => [
                    'description' => 'Account(s) to calculate profit for',
                    'require-value' => true
                ],
            ]
        ],
        'transaction' => [
            'description' => 'View transaction(s)',
            'options' => [
                'export-csv' => [
                    'description' => 'Generate and export CSV file',
                    'default' => false,
                    'require-value' => false
                ],
                'bank' => [
                    'description' => 'Bank(s) to include',
                    'require-value' => true
                ],
                'account' => [
                    'description' => 'Account(s) to include',
                    'require-value' => true
                ],
                'date-from' => [
                    'description' => 'From date to include',
                    'require-value' => true
                ],
                'date-to' => [
                    'description' => 'End date to include',
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
                'display-log' => [
                    'description' => 'Display logs related to how transactions are handled and more',
                    'default' => false,
                    'require-value' => false
                ]
            ]
        ],
        'fetch-historical-price' => [
            'description' => 'Fetch historical price of asset(s)',
            'options' => [
                'bank' => [
                    'description' => 'Bank(s) to include',
                    'require-value' => true
                ],
                'account' => [
                    'description' => 'Account(s) to include',
                    'require-value' => true
                ],
                'date-from' => [
                    'description' => 'From date to include',
                    'require-value' => true
                ],
                'date-to' => [
                    'description' => 'End date to include',
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
            ]
        ],
        'analyze' => [
            'description' => 'Analyze investment',
            'options' => [
                'bank' => [
                    'description' => 'Bank(s) to include',
                    'require-value' => true
                ],
                'account' => [
                    'description' => 'Account(s) to include',
                    'require-value' => true
                ],
                'date-from' => [
                    'description' => 'From date to include',
                    'require-value' => true
                ],
                'date-to' => [
                    'description' => 'End date to include',
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
            ]
        ],
    ];
}
