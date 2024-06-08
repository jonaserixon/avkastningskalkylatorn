<?php

namespace src\Command;

use src\DataStructure\Transaction;
use src\Enum\TransactionType;
use src\Service\FileManager\Exporter;
use src\Service\PPExporter;
use src\Service\ProfitCalculator;
use src\Service\Transaction\TransactionLoader;
use src\View\Logger;
use src\View\TextColorizer;
use stdClass;

class TransactionCommand extends CommandProcessor
{
    /** @var mixed[] */
    private array $options;

    /**
     * @param mixed[] $options
     */
    public function __construct(array $options)
    {
        $this->options = $options;

        parent::__construct();
    }

    public function getParsedOptions(): stdClass
    {
        $commandOptions = $this->commands['transaction']['options'];

        $options = new stdClass();
        $options->exportCsv = $this->options['export-csv'] ?? $commandOptions['export-csv']['default'];
        $options->bank = $this->options['bank'] ?? null;
        $options->isin = $this->options['isin'] ?? null;
        $options->asset = $this->options['asset'] ?? null;
        $options->dateFrom = $this->options['date-from'] ?? null;
        $options->dateTo = $this->options['date-to'] ?? null;
        $options->currentHoldings = $this->options['current-holdings'] ?? $commandOptions['current-holdings']['default'];
        $options->cashFlow = $this->options['cash-flow'] ?? null;
        $options->account = $this->options['account'] ?? null;
        $options->displayLog = $this->options['display-log'] ?? $commandOptions['display-log']['default'];

        return $options;
    }

    public function execute(): void
    {
        $options = $this->getParsedOptions();

        $transactionLoader = new TransactionLoader(
            $options->bank,
            $options->isin,
            $options->asset,
            $options->dateFrom,
            $options->dateTo,
            $options->currentHoldings,
            $options->account
        );

        $transactions = $transactionLoader->getTransactions();
        $assets = $transactionLoader->getFinancialAssets($transactions);

        // $this->exportSecuritiesToPP($assets, $options->exportCsv);
        // $this->exportFeesToPP($transactions, $options->exportCsv);
        // $this->exportAccountTransactionsToPP($transactions, $options->exportCsv);
        // $this->exportPortfolioTransactionsToPP($transactions, $options->exportCsv);
        // $this->exportDividendsToPP($transactions, $options->exportCsv);

        $ppExporter = new PPExporter($assets, $transactions, $options->exportCsv);

        $ppExporter->exportNordnetDividends();
        $ppExporter->exportNordnetAccountTransactions();
        $ppExporter->exportNordnetPortfolioTransactions();
        $ppExporter->exportNordnetFees();

        /*
        $ppExporter->exportAvanzaPortfolioTransactions();
        $ppExporter->exportAvanzaAccountTransactions();
        $ppExporter->exportAvanzaDividends();
        $ppExporter->exportAvanzaFees();
        */

        return;

        if ($options->cashFlow) {
            $assets = $transactionLoader->getFinancialAssets($transactions);

            $profitCalculator = new ProfitCalculator($options->currentHoldings);
            $result = $profitCalculator->calculate($assets, $transactionLoader->overview);

            foreach ($result->overview->cashFlows as $cashFlow) {
                $res = $cashFlow->getDateString() . ' | ';
                $res .= TextColorizer::colorText($cashFlow->getBankName(), 'grey') . ' | ';
                $res .= TextColorizer::colorText($cashFlow->account, 'green') . ' | ';
                $res .= TextColorizer::colorText($cashFlow->name, 'pink') . ' | ';
                $res .= TextColorizer::colorText($cashFlow->getTypeName(), 'yellow') . ' | ';
                $res .= TextColorizer::colorText($this->presenter->formatNumber($cashFlow->rawAmount), 'cyan');

                echo $res . PHP_EOL;
            }

            if ($options->exportCsv) {
                $cashFlowArray = [];
                foreach ($result->overview->cashFlows as $cashFlow) {
                    $amount = $cashFlow->rawAmount;
                    if ($cashFlow->getTypeName() === 'deposit') {
                        $amount = $amount * -1;
                    } elseif ($cashFlow->getTypeName() === 'withdrawal') {
                        $amount = abs($amount);
                    }
                    if (!in_array($cashFlow->type, [
                        TransactionType::DEPOSIT,
                        TransactionType::WITHDRAWAL,
                        TransactionType::DIVIDEND,
                        TransactionType::CURRENT_HOLDING,
                        TransactionType::FEE,
                        TransactionType::FOREIGN_WITHHOLDING_TAX,
                        TransactionType::RETURNED_FOREIGN_WITHHOLDING_TAX
                    ])) {
                        continue;
                    }
                    $cashFlowArray[] = [
                        $cashFlow->getDateString(),
                        $cashFlow->getBankName(),
                        $cashFlow->account,
                        $cashFlow->name,
                        $cashFlow->getTypeName(),
                        $amount
                    ];
                }
                $headers = ['Datum', 'Bank', 'Konto', 'Namn', 'Typ', 'Belopp'];
                Exporter::exportToCsv($headers, $cashFlowArray, 'cash_flow');
            }
        } else {
            echo 'Datum | Bank | Konto | Namn | Typ | Belopp | Antal | Pris' . PHP_EOL;
            foreach ($transactions as $transaction) {
                $res = $transaction->getDateString() . ' | ';
                $res .= TextColorizer::colorText($transaction->getBankName(), 'grey') . ' | ';
                $res .= TextColorizer::colorText($transaction->account, 'green') . ' | ';
                $res .= TextColorizer::colorText($transaction->name . " ({$transaction->isin})", 'pink') . ' | ';
                $res .= TextColorizer::colorText($transaction->getTypeName(), 'yellow') . ' | ';
                $res .= TextColorizer::colorText($this->presenter->formatNumber((float) $transaction->rawAmount), 'cyan') . ' | ';
                $res .= TextColorizer::colorText($this->presenter->formatNumber((float) $transaction->rawQuantity), 'grey') . ' | ';
                $res .= TextColorizer::backgroundColor($this->presenter->formatNumber((float) $transaction->rawPrice), 'green');

                echo $res . PHP_EOL;
            }

            if ($options->exportCsv) {
                $transactionArray = [];
                foreach ($transactions as $transaction) {
                    if (str_contains($transaction->account, 'ISK') ||
                        str_contains($transaction->account, '24497042') ||
                        str_contains($transaction->account, 'tmp') ||
                        str_contains($transaction->account, '-')
                    ) {
                        $account = 'ISK';
                    } elseif (str_contains($transaction->account, 'KF') ||
                              str_contains($transaction->account, '48273510')
                    ) {
                        $account = 'KF';
                    } else {
                        $account = 'Sparkonto';
                    }

                    if (!in_array($transaction->type, [
                        TransactionType::BUY,
                        TransactionType::SELL,
                        // TransactionType::DEPOSIT,
                        // TransactionType::WITHDRAWAL,
                        // TransactionType::DIVIDEND,
                        // TransactionType::FEE,
                        // TransactionType::FOREIGN_WITHHOLDING_TAX,
                        // TransactionType::RETURNED_FOREIGN_WITHHOLDING_TAX
                    ])) {
                        continue;
                    }

                    // 48273577 = sparkonto


                    $transactionArray[] = [
                        $transaction->getDateString(),
                        ucfirst(strtolower($transaction->getBankName())),
                        $account,
                        $transaction->name,
                        $transaction->isin,
                        ucfirst($transaction->getTypeName()),
                        abs($transaction->rawAmount),
                        abs($transaction->rawQuantity),
                        abs($transaction->rawPrice)
                    ];
                }
                $headers = ['Datum', 'Bank', 'Konto', 'Namn', 'ISIN', 'Typ', 'Belopp', 'Antal', 'Pris'];
                Exporter::exportToCsv($headers, $transactionArray, 'transactions');
            }
        }

        Logger::getInstance()->printInfos();

        if ($options->displayLog) {
            Logger::getInstance()
                ->printNotices()
                ->printWarnings();
        }
    }

    public function exportSecuritiesToPP(array $assets, bool $exportCsv = false): void
    {
        $tickers = file_get_contents(ROOT_PATH . '/data/tmp/tickers.json');
        $tickers = json_decode($tickers, true);

        $isinGroupedTickers = [];
        $assetList = [];
        foreach ($tickers as $ticker) {
            $isinGroupedTickers[$ticker['isin']][] = $ticker;
            $assetList[] = [
                $ticker['name'],
                $ticker['isin'],
                $ticker['ticker'],
                $ticker['currency']
            ];
        }

        // $assetList = [];
        // foreach ($assets as $asset) {
        //     $assetList[] = [
        //         $asset->name,
        //         $asset->isin,
        //         $this->getCurrencyByIsin($asset->isin)
        //     ];
        // }

        // print_r($simpleList);
        // exit;

        if ($exportCsv) {
            Exporter::exportToCsv(
                [
                    'Security Name',
                    'ISIN',
                    'Ticker Symbol',
                    'Currency'
                ],
                $assetList,
                'securities',
                ';'
            );
        } else {
            // echo 'Security Name | ISIN | Currency' . PHP_EOL;
            // foreach ($assetList as $asset) {
            //     echo $asset[0] . ' | ' . $asset[1] . ' | ' . $asset[2] . PHP_EOL;
            // }
        }
    }

    /**
     * @param Transaction[] $transactions
     */
    public function exportFeesToPP(array $transactions, bool $exportCsv = false): void
    {
        $tickers = file_get_contents(ROOT_PATH . '/data/tmp/tickers.json');
        $tickers = json_decode($tickers, true);

        $isinGroupedTickers = [];
        foreach ($tickers as $ticker) {
            $isinGroupedTickers[$ticker['isin']][] = $ticker;
        }

        $transactionArray = [];
        foreach ($transactions as $transaction) {
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

                    $value, // Value
                    'SEK', // Transaction Currency

                    $grossAmount, // Gross Amount
                    $currency['currency'], // Currency Gross Amount

                    $exchangeRate
                ];
            }
        }

        // exit;

        if ($exportCsv) {
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
                'fees_transactions',
                ';'
            );
        } else {
            print_r($transactionArray);
            exit;
        }
    }

    public function exportAccountTransactionsToPP(array $transactions, bool $exportCsv = false): void
    {
        $tickers = file_get_contents(ROOT_PATH . '/data/tmp/tickers.json');
        $tickers = json_decode($tickers, true);

        $isinGroupedTickers = [];
        foreach ($tickers as $ticker) {
            $isinGroupedTickers[$ticker['isin']][] = $ticker;
        }

        $transactionArray = [];
        foreach ($transactions as $transaction) {
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
                abs($transaction->rawAmount),
                $transaction->currency,
            ];
        }

        // print_r($transactionArray);
        // exit;

        if ($exportCsv) {
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
                'account_transactions',
                ';'
            );
        } else {
            // echo 'Date | Cash Account | Note | Type | Value' . PHP_EOL;
            // foreach ($transactionArray as $transaction) {
            //     echo $transaction[0] . ' | ' . $transaction[1] . ' | ' . $transaction[2] . ' | ' . $transaction[3] . ' | ' . $transaction[4] . PHP_EOL;
            // }
        }
    }

    /**
     * @param Transaction[] $transactions
     */
    public function exportPortfolioTransactionsToPP(array $transactions, bool $exportCsv = false): void
    {
        $tickers = file_get_contents(ROOT_PATH . '/data/tmp/tickers.json');
        $tickers = json_decode($tickers, true);

        $isinGroupedTickers = [];
        foreach ($tickers as $ticker) {
            $isinGroupedTickers[$ticker['isin']][] = $ticker;
        }

        $skippedTransactions = [];
        $transactionArray = [];
        foreach ($transactions as $transaction) {
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
                            $transaction->isin
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
                    $value,
                    $grossAmount,
                    abs($transaction->rawQuantity),
                    $transactionCurrency,
                    $currencyGrossAmount,
                    $exchangeRate,
                    abs($transaction->commission)
                ];
            }
        }

        // print_r($transactionArray);
        // exit;

        if ($exportCsv) {
            echo 'Skipped transactions (please enter manually):' . PHP_EOL;
            print_r($skippedTransactions);

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
                'portfolio_transactions',
                ';'
            );
        } else {
            // echo 'Date | Cash Account | Securities Account | Security Name | ISIN | Type | Value | Shares | Currency' . PHP_EOL;
            // foreach ($transactionArray as $transaction) {
            //     echo $transaction[0] . ' | ' . $transaction[1] . ' | ' . $transaction[2] . ' | ' . $transaction[3] . ' | ' . $transaction[4] . ' | ' . $transaction[5] . ' | ' . $transaction[6] . ' | ' . $transaction[7] . ' | ' . $transaction[8] . PHP_EOL;
            // }
        }
    }

    public function exportDividendsToPP(array $transactions, bool $exportCsv = false): void
    {
        $tickers = file_get_contents(ROOT_PATH . '/data/tmp/tickers.json');
        $tickers = json_decode($tickers, true);

        $isinGroupedTickers = [];
        foreach ($tickers as $ticker) {
            $isinGroupedTickers[$ticker['isin']][] = $ticker;
        }

        $manualTransactions = [];
        $transactionArray = [];
        foreach ($transactions as $transaction) {
            if ($transaction->type !== TransactionType::DIVIDEND) {
                continue;
            }

            $currencies = $isinGroupedTickers[$transaction->isin];

            foreach ($currencies as $currency) {
                // $value = abs($transaction->rawAmount);
                $grossAmount = abs($transaction->rawAmount);

                $exchangeRate = 1;
                if ($currency['currency'] !== 'SEK') {
                    $exchangeRate = abs($transaction->rawAmount) / (abs($transaction->rawQuantity) * abs($transaction->rawPrice));

                    // $value = (abs($transaction->rawQuantity) * abs($transaction->rawPrice));
                    $grossAmount = (abs($transaction->rawQuantity) * abs($transaction->rawPrice));

                    // TODO: dessa måste jag lägga in manuellt pga avanza skickar inte med originalvalutan
                    if (in_array($currency['currency'], ['USD', 'CAD', 'EUR']) && $exchangeRate < 3) {
                        // $manualTransactions[$transaction->isin][] = [
                        //     $transaction->getDateString(),
                        //     $transaction->name,
                        //     $transaction->isin,
                        //     abs($transaction->rawAmount) . ' SEK',
                        //     abs($transaction->rawQuantity) . ' st',
                        //     $currency['ticker']
                        // ];
                        // continue;
                    }
                }

                $transactionArray[] = [
                    $transaction->getDateString(),
                    ucfirst(strtolower($transaction->getBankName())) . ' SEK',
                    ucfirst(strtolower($transaction->getBankName())),
                    $transaction->name,
                    $transaction->isin,
                    ucfirst($transaction->getTypeName()),
                    abs($transaction->rawAmount), // $value
                    $grossAmount,
                    abs($transaction->rawQuantity),
                    'SEK',
                    $currency['currency'],
                    $exchangeRate
                ];
            }
        }

        // print_r($manualTransactions);
        // print_r(count($manualTransactions));
        // exit;

        if ($exportCsv) {
            echo 'Manual transactions (please enter manually):' . PHP_EOL;
            print_r($manualTransactions);
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
                'dividends',
                ';'
            );
        } else {
            // echo 'Date | Cash Account | Securities Account | Security Name | ISIN | Type | Value | Shares' . PHP_EOL;
            // foreach ($transactionArray as $transaction) {
            //     echo $transaction[0] . ' | ' . $transaction[1] . ' | ' . $transaction[2] . ' | ' . $transaction[3] . ' | ' . $transaction[4] . ' | ' . $transaction[5] . ' | ' . $transaction[6] . ' | ' . $transaction[7] . PHP_EOL;
            // }
        }
    }

    protected function getCurrencyByIsin(string $isin): string
    {
        if (str_starts_with($isin, 'SE')) {
            return 'SEK';
        }

        if (str_starts_with($isin, 'FI')) {
            return 'EUR';
        }

        if (str_starts_with($isin, 'US')) {
            return 'USD';
        }

        if (str_starts_with($isin, 'NO')) {
            return 'NOK';
        }

        if (str_starts_with($isin, 'DK')) {
            return 'DKK';
        }

        if (str_starts_with($isin, 'DE')) {
            return 'EUR';
        }

        if (str_starts_with($isin, 'GB')) {
            return 'GBP';
        }

        if (str_starts_with($isin, 'CA')) {
            return 'CAD';
        }

        return 'SEK';
        // return !str_starts_with($isin, 'SE');
    }
}
