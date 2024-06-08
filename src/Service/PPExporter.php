<?php

namespace src\Service;

use src\DataStructure\Transaction;
use src\Enum\TransactionType;
use src\Service\FileManager\Exporter;

class PPExporter
{
    private bool $exportCsv;
    private array $tickers;
    private array $assets;
    private array $transactions;

    public function __construct(array $assets, array $transactions, bool $exportCsv = false)
    {
        $this->exportCsv = $exportCsv;
        $this->assets = $assets;
        $this->transactions = $transactions;

        $tickers = file_get_contents(ROOT_PATH . '/data/tmp/tickers.json');
        $this->tickers = json_decode($tickers, true);
    }

    /**
     * @param Transaction[] $transactions
     */
    public function exportSecurities(): void
    {

    }

    /**
     * @param Transaction[] $transactions
     */
    public function exportAvanzaDividends(): void
    {
        $isinGroupedTickers = [];
        foreach ($this->tickers as $ticker) {
            $isinGroupedTickers[$ticker['isin']][] = $ticker;
        }

        $manualTransactions = [];
        $transactionArray = [];
        foreach ($this->transactions as $transaction) {
            if ($transaction->type !== TransactionType::DIVIDEND) {
                continue;
            }

            $currencies = $isinGroupedTickers[$transaction->isin];

            foreach ($currencies as $currency) {
                $value = abs($transaction->rawAmount);

                $exchangeRate = 1;
                /*
                $grossAmount = abs($transaction->rawAmount);
                if ($currency['currency'] !== 'SEK') {
                    $exchangeRate = $value - abs($transaction->commission) / (abs($transaction->rawQuantity) * abs($transaction->rawPrice));
                    $grossAmount = (abs($transaction->rawQuantity) * abs($transaction->rawPrice));

                    // TODO: dessa måste jag lägga in manuellt pga avanza skickar inte med originalvalutan
                    if (in_array($currency['currency'], ['USD', 'CAD', 'EUR']) && $exchangeRate < 3) {
                        $manualTransactions[$transaction->isin][] = [
                            $transaction->getDateString(),
                            $transaction->name,
                            $transaction->isin,
                            abs($transaction->rawAmount) . ' SEK',
                            abs($transaction->rawQuantity) . ' st',
                            $currency['ticker']
                        ];
                        continue;
                    }
                }
                */

                $transactionCurrency = 'SEK';
                $currencyGrossAmount = $currency['currency'];

                $transactionArray[] = [
                    $transaction->getDateString(),
                    ucfirst(strtolower($transaction->getBankName())) . ' SEK',
                    ucfirst(strtolower($transaction->getBankName())),
                    $transaction->name,
                    $transaction->isin,
                    ucfirst($transaction->getTypeName()),
                    str_replace('.', ',', $value),
                    str_replace('.', ',', $value),
                    abs($transaction->rawQuantity),
                    $transactionCurrency,
                    $currencyGrossAmount,
                    str_replace('.', ',', $exchangeRate)
                ];
            }
        }

        echo 'Manual transactions (please enter manually):' . PHP_EOL;
        print_r($manualTransactions);

        if ($this->exportCsv) {
            Exporter::exportToCsv(
                [
                    'Date',
                    'Cash Account',
                    'Securities Account',
                    'Security Name',
                    'ISIN',
                    'Type',
                    'Value', // det faktiska beloppet ("Belopp" hos avanza då courtage redan är inbakat)
                    'Gross Amount', // Originalbeloppet i ursprungsvalutan
                    'Shares',
                    'Transaction Currency',
                    'Currency Gross Amount',
                    'Exchange Rate'
                ],
                $transactionArray,
                'avanza_dividends',
                ';'
            );
        }
    }

    /**
     * @param Transaction[] $transactions
     */
    public function exportAvanzaPortfolioTransactions(): void
    {
        $isinGroupedTickers = [];
        foreach ($this->tickers as $ticker) {
            $isinGroupedTickers[$ticker['isin']][] = $ticker;
        }

        $skippedTransactions = [];
        $transactionArray = [];
        foreach ($this->transactions as $transaction) {
            if (!in_array($transaction->type, [
                TransactionType::BUY,
                TransactionType::SELL
            ])) {
                continue;
            }

            $currencies = $isinGroupedTickers[$transaction->isin];
            foreach ($currencies as $currency) {
                $value = abs($transaction->rawAmount);
                $grossAmount = abs($transaction->rawAmount);

                $exchangeRate = 1;
                if ($currency['currency'] !== 'SEK') {
                    $exchangeRate = ($value - abs($transaction->commission)) / (abs($transaction->rawQuantity) * abs($transaction->rawPrice));

                    if (in_array($currency['currency'], ['USD', 'CAD', 'EUR']) && $exchangeRate < 2) {
                        $skippedTransactions[$transaction->isin][] = [
                            $transaction->getDateString(),
                            $transaction->name,
                            $transaction->isin,
                            $transaction->type
                        ];
                        continue;
                    }
                }

                $cashAccount = ucfirst(strtolower($transaction->getBankName())) . ' SEK';
                $securitiesAccount = ucfirst(strtolower($transaction->getBankName()));
                $transactionCurrency = 'SEK';
                $currencyGrossAmount = $currency['currency'];

                $transactionArray[] = [
                    $transaction->getDateString(),
                    $cashAccount,
                    $securitiesAccount,
                    $transaction->name,
                    $transaction->isin,
                    ucfirst($transaction->getTypeName()),
                    str_replace('.', ',', $value),
                    str_replace('.', ',', $grossAmount),
                    str_replace('.', ',', abs($transaction->rawQuantity)),
                    $transactionCurrency,
                    $currencyGrossAmount,
                    str_replace('.', ',', $exchangeRate),
                    str_replace('.', ',', abs($transaction->commission))
                ];
            }
        }

        echo 'Skipped transactions (please enter manually):' . PHP_EOL;
        print_r($skippedTransactions);

        if ($this->exportCsv) {
            Exporter::exportToCsv(
                [
                    'Date',
                    'Cash Account',
                    'Securities Account',
                    'Security Name',
                    'ISIN',
                    'Type',
                    'Value',
                    'Gross Amount',
                    'Shares',
                    'Transaction Currency',
                    'Currency Gross Amount',
                    'Exchange Rate',
                    'Fees'
                ],
                $transactionArray,
                'avanza_portfolio_transactions',
                ';'
            );
        }
    }

    /**
     * @param Transaction[] $transactions
     */
    public function exportAvanzaAccountTransactions(): void
    {
        $isinGroupedTickers = [];
        foreach ($this->tickers as $ticker) {
            $isinGroupedTickers[$ticker['isin']][] = $ticker;
        }

        $transactionArray = [];
        foreach ($this->transactions as $transaction) {
            if (!in_array($transaction->type, [
                TransactionType::DEPOSIT,
                TransactionType::WITHDRAWAL,
                TransactionType::FEE,
                TransactionType::INTEREST,
                TransactionType::TAX,
                TransactionType::RETURNED_FOREIGN_WITHHOLDING_TAX
            ])) {
                continue;
            }

            $type = ucfirst($transaction->getTypeName());
            if ($transaction->type === TransactionType::WITHDRAWAL) {
                $type = 'Removal';
            } elseif ($transaction->type === TransactionType::RETURNED_FOREIGN_WITHHOLDING_TAX) {
                $type = 'Tax Refund';
            } elseif ($transaction->type === TransactionType::FOREIGN_WITHHOLDING_TAX) {
                $type = 'Taxes';
            } elseif ($transaction->type === TransactionType::FEE) {
                if ($transaction->rawAmount < 0) {
                    $type = 'Fees';
                } else {
                    $type = 'Fees Refund';
                }
            } elseif ($transaction->type === TransactionType::INTEREST) {
                if ($transaction->rawAmount > 0) {
                    $type = 'Interest';
                } else {
                    $type = 'Interest Charge';
                }
            } elseif ($transaction->type === TransactionType::TAX) {
                if ($transaction->rawAmount < 0) {
                    $type = 'Taxes';
                } else {
                    $type = 'Tax Refund';
                }
            }

            // Exkludera allt som inte hör till ett specifikt värdepapper. Hanteras i exportFees.
            if (!empty($transaction->isin)) {
                continue;
            }

            // TODO: kolla närmare på vilka transaktioner som inte har kommit med här.
            // if (!empty($transaction->isin)) {
            //     $currencies = $isinGroupedTickers[$transaction->isin];
            //     $value = abs($transaction->rawAmount);
            //     // $grossAmount = abs($transaction->rawAmount);

            //     $exchangeRate = 1;
            //     if ($currency['currency'] !== 'SEK') {
            //         $exchangeRate = abs($transaction->rawAmount) / (abs($transaction->rawQuantity) * abs($transaction->rawPrice));

            //         $value = (abs($transaction->rawQuantity) * abs($transaction->rawPrice));
            //         // $grossAmount = abs($transaction->rawAmount);
            //     }
            // }

            $note = $transaction->name;
            if (empty($note)) {
                $note = $transaction->description;
            }

            $transactionArray[] = [
                $transaction->getDateString(),
                ucfirst(strtolower($transaction->getBankName())) . ' SEK',
                ucfirst(strtolower($transaction->getBankName())),
                $note,
                $type,
                str_replace('.', ',', abs($transaction->rawAmount)),
                $transaction->currency,
            ];
        }

        // print_r($transactionArray);
        // exit;

        if ($this->exportCsv) {
            Exporter::exportToCsv(
                [
                    'Date',
                    'Cash Account',
                    'Securities Account',
                    'Note',
                    'Type',
                    'Value',
                    'Transaction Currency',

                    // 'Date',
                    // 'Cash Account',
                    // 'Securities Account',
                    // 'Note',
                    // // 'ISIN',
                    // 'Type',
                    // 'Value', // det faktiska beloppet ("Belopp" hos avanza då courtage redan är inbakat)
                    // // 'Gross Amount', // Originalbeloppet i ursprungsvalutan
                    // 'Shares',
                    // 'Transaction Currency',
                    // 'Currency Gross Amount',
                    // 'Exchange Rate'
                ],
                $transactionArray,
                'avanza_account_transactions',
                ';'
            );
        }
    }

    /**
     * @param Transaction[] $transactions
     */
    public function exportAvanzaFees(): void
    {
        $isinGroupedTickers = [];
        foreach ($this->tickers as $ticker) {
            $isinGroupedTickers[$ticker['isin']][] = $ticker;
        }

        $transactionArray = [];
        foreach ($this->transactions as $transaction) {
            if (!in_array($transaction->type, [
                TransactionType::FEE,
                TransactionType::FOREIGN_WITHHOLDING_TAX,
            ])) {
                continue;
            }

            $type = ucfirst($transaction->getTypeName());
            if ($transaction->type === TransactionType::FOREIGN_WITHHOLDING_TAX) {
                $type = 'Taxes';
            } elseif ($transaction->type === TransactionType::FEE) {
                if ($transaction->rawAmount < 0) {
                    $type = 'Fees';
                } else {
                    $type = 'Fees Refund';
                }
            }

            // Allt som inte är kopplat till ett värdepapper hanteras i exportAccountTransactionsToPP.
            if (empty($transaction->isin)) {
                continue;
            }
          

            $currencies = $isinGroupedTickers[$transaction->isin];

            foreach ($currencies as $currency) {
                $value = abs($transaction->rawAmount);
                $grossAmount = abs($transaction->rawAmount);

                $exchangeRate = 1;
                if ($currency['currency'] !== 'SEK') {
                    // $exchangeRate = abs($transaction->rawAmount) / (abs($transaction->rawQuantity) * abs($transaction->rawPrice));
                    // $grossAmount = (abs($transaction->rawQuantity) * abs($transaction->rawPrice));
                }

                $transactionArray[] = [
                    $transaction->getDateString(),
                    ucfirst(strtolower($transaction->getBankName())) . ' SEK',
                    ucfirst(strtolower($transaction->getBankName())),
                    $transaction->name,
                    $transaction->isin,
                    $type,

                    str_replace('.', ',', $value), // Value
                    'SEK', // Transaction Currency

                    str_replace('.', ',', $grossAmount), // Gross Amount
                    $currency['currency'], // Currency Gross Amount

                    $exchangeRate
                ];
            }
        }

        // print_r($transactionArray);
        // exit;

        if ($this->exportCsv) {
            Exporter::exportToCsv(
                [
                    'Date',
                    'Cash Account',
                    'Securities Account',
                    'Note',
                    'ISIN',
                    'Type',
                    'Value', // det faktiska beloppet ("Belopp" hos avanza då courtage redan är inbakat)
                    'Transaction Currency',
                    'Gross Amount', // Originalbeloppet i ursprungsvalutan
                    'Currency Gross Amount',
                    'Exchange Rate'
                ],
                $transactionArray,
                'avanza_fees_transactions',
                ';'
            );
        }
    }

    public function exportNordnetDividends(): void
    {
        // Info: does not handle cancelled transactions
        $isinGroupedTickers = [];
        foreach ($this->tickers as $ticker) {
            $isinGroupedTickers[$ticker['isin']][] = $ticker;
        }

        $manualTransactions = [];
        $transactionArray = [];
        foreach ($this->transactions as $transaction) {
            if ($transaction->type !== TransactionType::DIVIDEND) {
                continue;
            }

            $note = null;

            $currencies = $isinGroupedTickers[$transaction->isin];

            foreach ($currencies as $currency) {
                $value = abs($transaction->rawAmount);

                if ($transaction->rawAmount < 0) {
                    $note = 'Suspicious dividend: ' . $transaction->getDateString() . ' ' . $transaction->name . ' ' . $transaction->isin . PHP_EOL;
                }

                $exchangeRate = 1;
                /*
                $grossAmount = abs($transaction->rawAmount);
                if ($currency['currency'] !== 'SEK') {
                    $exchangeRate = $value - abs($transaction->commission) / (abs($transaction->rawQuantity) * abs($transaction->rawPrice));
                    $grossAmount = (abs($transaction->rawQuantity) * abs($transaction->rawPrice));

                    // TODO: dessa måste jag lägga in manuellt pga avanza skickar inte med originalvalutan
                    if (in_array($currency['currency'], ['USD', 'CAD', 'EUR']) && $exchangeRate < 3) {
                        $manualTransactions[$transaction->isin][] = [
                            $transaction->getDateString(),
                            $transaction->name,
                            $transaction->isin,
                            abs($transaction->rawAmount) . ' SEK',
                            abs($transaction->rawQuantity) . ' st',
                            $currency['ticker']
                        ];
                        continue;
                    }
                }
                */

                $transactionCurrency = 'SEK';
                $currencyGrossAmount = $currency['currency'];

                // TODO: add note.

                $transactionArray[] = [
                    $transaction->getDateString(),
                    ucfirst(strtolower($transaction->getBankName())) . ' SEK',
                    ucfirst(strtolower($transaction->getBankName())),
                    $transaction->name,
                    $transaction->isin,
                    ucfirst($transaction->getTypeName()),
                    str_replace('.', ',', $value),
                    str_replace('.', ',', $value),
                    abs($transaction->rawQuantity),
                    $transactionCurrency,
                    $currencyGrossAmount,
                    str_replace('.', ',', $exchangeRate)
                ];
            }
        }

        echo 'Manual transactions (please enter manually):' . PHP_EOL;
        print_r($manualTransactions);

        if ($this->exportCsv) {
            Exporter::exportToCsv(
                [
                    'Date',
                    'Cash Account',
                    'Securities Account',
                    'Security Name',
                    'ISIN',
                    'Type',
                    'Value', // det faktiska beloppet ("Belopp" hos avanza då courtage redan är inbakat)
                    'Gross Amount', // Originalbeloppet i ursprungsvalutan
                    'Shares',
                    'Transaction Currency',
                    'Currency Gross Amount',
                    'Exchange Rate'
                ],
                $transactionArray,
                'nordnet_dividends',
                ';'
            );
        }
    }

    public function exportNordnetPortfolioTransactions(): void
    {
        $isinGroupedTickers = [];
        foreach ($this->tickers as $ticker) {
            $isinGroupedTickers[$ticker['isin']][] = $ticker;
        }

        $skippedTransactions = [];
        $transactionArray = [];
        foreach ($this->transactions as $transaction) {
            if (!in_array($transaction->type, [
                TransactionType::BUY,
                TransactionType::SELL
            ])) {
                continue;
            }

            $currencies = $isinGroupedTickers[$transaction->isin];
            foreach ($currencies as $currency) {
                $value = abs($transaction->rawAmount);
                $grossAmount = abs($transaction->rawAmount);

                $exchangeRate = 1;
                if ($currency['currency'] !== 'SEK') {
                    $exchangeRate = ($value - abs($transaction->commission)) / (abs($transaction->rawQuantity) * abs($transaction->rawPrice));
                    // TODO: kan använda nordnets egna växlingskurs här
                    if (in_array($currency['currency'], ['USD', 'CAD', 'EUR']) && $exchangeRate < 2) {
                        $skippedTransactions[$transaction->isin][] = [
                            $transaction->getDateString(),
                            $transaction->name,
                            $transaction->isin,
                            $transaction->type
                        ];
                        continue;
                    }
                }

                $cashAccount = ucfirst(strtolower($transaction->getBankName())) . ' SEK';
                $securitiesAccount = ucfirst(strtolower($transaction->getBankName()));
                $transactionCurrency = 'SEK';
                $currencyGrossAmount = $currency['currency'];

                $transactionArray[] = [
                    $transaction->getDateString(),
                    $cashAccount,
                    $securitiesAccount,
                    $transaction->name,
                    $transaction->isin,
                    ucfirst($transaction->getTypeName()),
                    str_replace('.', ',', $value),
                    str_replace('.', ',', $grossAmount),
                    str_replace('.', ',', abs($transaction->rawQuantity)),
                    $transactionCurrency,
                    $currencyGrossAmount,
                    str_replace('.', ',', $exchangeRate),
                    str_replace('.', ',', abs($transaction->commission))
                ];
            }
        }

        echo 'Skipped transactions (please enter manually):' . PHP_EOL;
        print_r($skippedTransactions);

        if ($this->exportCsv) {
            Exporter::exportToCsv(
                [
                    'Date',
                    'Cash Account',
                    'Securities Account',
                    'Security Name',
                    'ISIN',
                    'Type',
                    'Value',
                    'Gross Amount',
                    'Shares',
                    'Transaction Currency',
                    'Currency Gross Amount',
                    'Exchange Rate',
                    'Fees'
                ],
                $transactionArray,
                'nordnet_portfolio_transactions',
                ';'
            );
        }
    }

    public function exportNordnetAccountTransactions(): void
    {
        $isinGroupedTickers = [];
        foreach ($this->tickers as $ticker) {
            $isinGroupedTickers[$ticker['isin']][] = $ticker;
        }

        $transactionArray = [];
        foreach ($this->transactions as $transaction) {
            if (!in_array($transaction->type, [
                TransactionType::DEPOSIT,
                TransactionType::WITHDRAWAL,
                TransactionType::FEE,
                TransactionType::INTEREST,
                TransactionType::TAX,
                TransactionType::RETURNED_FOREIGN_WITHHOLDING_TAX
            ])) {
                continue;
            }

            $type = ucfirst($transaction->getTypeName());
            if ($transaction->type === TransactionType::WITHDRAWAL) {
                $type = 'Removal';
            } elseif ($transaction->type === TransactionType::RETURNED_FOREIGN_WITHHOLDING_TAX) {
                $type = 'Tax Refund';
            } elseif ($transaction->type === TransactionType::FOREIGN_WITHHOLDING_TAX) {
                $type = 'Taxes';
            } elseif ($transaction->type === TransactionType::FEE) {
                if ($transaction->rawAmount < 0) {
                    $type = 'Fees';
                } else {
                    $type = 'Fees Refund';
                }
            } elseif ($transaction->type === TransactionType::INTEREST) {
                if ($transaction->rawAmount > 0) {
                    $type = 'Interest';
                } else {
                    $type = 'Interest Charge';
                }
            } elseif ($transaction->type === TransactionType::TAX) {
                if ($transaction->rawAmount < 0) {
                    $type = 'Taxes';
                } else {
                    $type = 'Tax Refund';
                }
            }

            // Exkludera allt som inte hör till ett specifikt värdepapper. Hanteras i exportFees.
            if (!empty($transaction->isin)) {
                continue;
            }

            // TODO: kolla närmare på vilka transaktioner som inte har kommit med här.
            // if (!empty($transaction->isin)) {
            //     $currencies = $isinGroupedTickers[$transaction->isin];
            //     $value = abs($transaction->rawAmount);
            //     // $grossAmount = abs($transaction->rawAmount);

            //     $exchangeRate = 1;
            //     if ($currency['currency'] !== 'SEK') {
            //         $exchangeRate = abs($transaction->rawAmount) / (abs($transaction->rawQuantity) * abs($transaction->rawPrice));

            //         $value = (abs($transaction->rawQuantity) * abs($transaction->rawPrice));
            //         // $grossAmount = abs($transaction->rawAmount);
            //     }
            // }

            $note = $transaction->name;
            if (empty($note)) {
                $note = $transaction->description;
            }

            $transactionArray[] = [
                $transaction->getDateString(),
                ucfirst(strtolower($transaction->getBankName())) . ' SEK',
                ucfirst(strtolower($transaction->getBankName())),
                $note,
                $type,
                str_replace('.', ',', abs($transaction->rawAmount)),
                $transaction->currency,
            ];
        }

        // print_r($transactionArray);
        // exit;

        if ($this->exportCsv) {
            Exporter::exportToCsv(
                [
                    'Date',
                    'Cash Account',
                    'Securities Account',
                    'Note',
                    'Type',
                    'Value',
                    'Transaction Currency',

                    // 'Date',
                    // 'Cash Account',
                    // 'Securities Account',
                    // 'Note',
                    // // 'ISIN',
                    // 'Type',
                    // 'Value', // det faktiska beloppet ("Belopp" hos avanza då courtage redan är inbakat)
                    // // 'Gross Amount', // Originalbeloppet i ursprungsvalutan
                    // 'Shares',
                    // 'Transaction Currency',
                    // 'Currency Gross Amount',
                    // 'Exchange Rate'
                ],
                $transactionArray,
                'nordnet_account_transactions',
                ';'
            );
        }
    }

    public function exportNordnetFees(): void
    {
        $isinGroupedTickers = [];
        foreach ($this->tickers as $ticker) {
            $isinGroupedTickers[$ticker['isin']][] = $ticker;
        }

        $transactionArray = [];
        foreach ($this->transactions as $transaction) {
            if (!in_array($transaction->type, [
                TransactionType::FEE,
                TransactionType::FOREIGN_WITHHOLDING_TAX,
            ])) {
                continue;
            }

            $note = null;

            $type = ucfirst($transaction->getTypeName());
            if ($transaction->type === TransactionType::FOREIGN_WITHHOLDING_TAX) {
                if ($transaction->rawAmount > 0 && !empty($transaction->isin)) {
                    $note = 'Suspicious foreign withholding tax: ' . $transaction->getDateString() . ' ' . $transaction->name . ' ' . $transaction->isin . PHP_EOL;
                }
                $type = 'Taxes';
            } elseif ($transaction->type === TransactionType::FEE) {
                if ($transaction->rawAmount < 0) {
                    $type = 'Fees';
                } else {
                    $type = 'Fees Refund';
                }
            }

            // Allt som inte är kopplat till ett värdepapper hanteras i exportAccountTransactionsToPP.
            if (empty($transaction->isin)) {
                continue;
            }
          

            $currencies = $isinGroupedTickers[$transaction->isin];

            foreach ($currencies as $currency) {
                $value = abs($transaction->rawAmount);
                $grossAmount = abs($transaction->rawAmount);

                $exchangeRate = 1;
                if ($currency['currency'] !== 'SEK') {
                    // $exchangeRate = abs($transaction->rawAmount) / (abs($transaction->rawQuantity) * abs($transaction->rawPrice));
                    // $grossAmount = (abs($transaction->rawQuantity) * abs($transaction->rawPrice));
                }

                // TODO: add note.

                $transactionArray[] = [
                    $transaction->getDateString(),
                    ucfirst(strtolower($transaction->getBankName())) . ' SEK',
                    ucfirst(strtolower($transaction->getBankName())),
                    $transaction->name,
                    $transaction->isin,
                    $type,

                    str_replace('.', ',', $value), // Value
                    'SEK', // Transaction Currency

                    str_replace('.', ',', $grossAmount), // Gross Amount
                    $currency['currency'], // Currency Gross Amount

                    $exchangeRate
                ];
            }
        }

        // print_r($transactionArray);
        // exit;

        if ($this->exportCsv) {
            Exporter::exportToCsv(
                [
                    'Date',
                    'Cash Account',
                    'Securities Account',
                    'Note',
                    'ISIN',
                    'Type',
                    'Value', // det faktiska beloppet ("Belopp" hos avanza då courtage redan är inbakat)
                    'Transaction Currency',
                    'Gross Amount', // Originalbeloppet i ursprungsvalutan
                    'Currency Gross Amount',
                    'Exchange Rate'
                ],
                $transactionArray,
                'nordnet_fees_transactions',
                ';'
            );
        }
    }
}
