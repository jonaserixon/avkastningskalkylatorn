<?php declare(strict_types=1);

namespace Avk\Service\FileManager;

use Avk\DataStructure\TickerInfo;
use Avk\DataStructure\Transaction;
use Avk\Enum\TransactionType;
use Avk\Service\API\Frankfurter\FrankfurterWrapper;
use Avk\Service\FileManager\Exporter;
use Avk\Service\Utility;
use Avk\View\Logger;
use Exception;

class PPExporter
{
    // TODO: för att slippa tvinga en "ticker.json" fil så kan man låta användaren skriva in saker via konsolen. Borde vara en option.
    private bool $exportCsv;

    /** @var TickerInfo[] */
    private array $tickers;

    /** @var Transaction[] */
    private array $transactions;

    /**
     * @param Transaction[] $transactions
     * @param bool $exportCsv
     */
    public function __construct(array $transactions, bool $exportCsv = false)
    {
        $this->exportCsv = $exportCsv;
        $this->transactions = $transactions;

        $this->tickers = $this->parseTickerInfo();
    }

    /**
     * @return TickerInfo[]
     */
    private function parseTickerInfo(): array
    {
        $tickers = Utility::jsonDecodeFromFile(ROOT_PATH . '/resources/tmp/tickers.json');
        if (!is_array($tickers)) {
            throw new Exception('Failed to parse tickers.json');
        }

        $tickerInfos = [];
        foreach ($tickers as $ticker) {
            $tickerInfo = new TickerInfo(
                $ticker->ticker,
                $ticker->isin,
                $ticker->name,
                $ticker->currency
            );

            $tickerInfos[] = $tickerInfo;
        }

        return $tickerInfos;
    }

    public function exportSecurities(): void
    {
        $assetList = [];
        foreach ($this->tickers as $ticker) {
            $assetList[] = $ticker->toArray();
        }

        // $assetList = [];
        // foreach ($assets as $asset) {
        //     $assetList[] = [
        //         $asset->name,
        //         $asset->isin,
        //         $this->getCurrencyByIsin($asset->isin)
        //     ];
        // }

        if ($this->exportCsv && !empty($assetList)) {
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
        }
    }

    public function exportAvanzaDividends(): void
    {
        $isinGroupedTickers = [];
        foreach ($this->tickers as $ticker) {
            $isinGroupedTickers[$ticker->isin][] = $ticker->toArray();
        }

        $transactionArray = [];
        foreach ($this->transactions as $transaction) {
            if ($transaction->type !== TransactionType::DIVIDEND) {
                continue;
            }

            if ($transaction->rawQuantity === null || $transaction->rawAmount === null) {
                Logger::getInstance()->addWarning('Missing quantity or amount for dividend transaction for ' . $transaction->getDateString() . ' ' . $transaction->name . ' ' . $transaction->isin);
                continue;
            }

            $currencies = $isinGroupedTickers[$transaction->isin];
            foreach ($currencies as $currency) {
                $value = abs($transaction->rawAmount);

                $exchangeRate = 1;
                $transactionCurrency = 'SEK';
                $currencyGrossAmount = $currency['currency'];

                $transactionArray[] = [
                    $transaction->getDateString(),
                    ucfirst(strtolower($transaction->getBankName())) . ' SEK',
                    ucfirst(strtolower($transaction->getBankName())),
                    $transaction->name,
                    $transaction->isin,
                    ucfirst($transaction->getTypeName()),
                    $this->replaceDotWithComma($value),
                    $this->replaceDotWithComma($value),
                    abs($transaction->rawQuantity),
                    $transactionCurrency,
                    $currencyGrossAmount,
                    $this->replaceDotWithComma($exchangeRate)
                ];
            }
        }

        if ($this->exportCsv && !empty($transactionArray)) {
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

    public function exportAvanzaPortfolioTransactions(): void
    {
        $isinGroupedTickers = [];
        foreach ($this->tickers as $ticker) {
            $isinGroupedTickers[$ticker->isin][] = $ticker->toArray();
        }

        /* $skippedTransactions = []; */
        $transactionArray = [];
        foreach ($this->transactions as $transaction) {
            if (!in_array($transaction->type, [
                TransactionType::BUY,
                TransactionType::SELL
            ])) {
                continue;
            }

            if ($transaction->rawQuantity === null) {
                Logger::getInstance()->addWarning('Missing quantity for transaction for ' . $transaction->getDateString() . ' ' . $transaction->name . ' ' . $transaction->isin);
                continue;
            }

            if ($transaction->rawPrice === null || $transaction->rawAmount === null) {
                Logger::getInstance()->addWarning('Missing price or amount for transaction for ' . $transaction->getDateString() . ' ' . $transaction->name . ' ' . $transaction->isin);
                continue;
            }

            $currencies = $isinGroupedTickers[$transaction->isin];
            foreach ($currencies as $currency) {
                $value = abs($transaction->rawAmount);
                $grossAmount = abs($transaction->rawAmount);

                if ($transaction->commission === null) {
                    $commission = 0;
                } else {
                    $commission = abs($transaction->commission);
                }

                $exchangeRate = 1;
                if ($currency['currency'] !== 'SEK') {
                    $exchangeRate = ($value - $commission) / (abs($transaction->rawQuantity) * abs($transaction->rawPrice));

                    if (in_array($currency['currency'], ['USD', 'CAD', 'EUR']) && $exchangeRate < 2) {
                        $frankfurter = new FrankfurterWrapper();
                        $exchangeRate = $frankfurter->getExchangeRateByCurrencyAndDate($currency['currency'], $transaction->getDateString());

                        echo 'Using Frankfurter API to fetch exchange rate for transaction ' . $transaction->getDateString() . ' ' . $transaction->name . ' ' . $transaction->isin . PHP_EOL;
                        /*
                        $skippedTransactions[$transaction->isin][] = [
                            $transaction->getDateString(),
                            $transaction->name,
                            $transaction->isin,
                            $transaction->type
                        ];
                        continue;
                        */
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
                    $this->replaceDotWithComma($value),
                    $this->replaceDotWithComma($grossAmount),
                    $this->replaceDotWithComma(abs($transaction->rawQuantity)),
                    $transactionCurrency,
                    $currencyGrossAmount,
                    $this->replaceDotWithComma($exchangeRate),
                    $this->replaceDotWithComma($commission)
                ];
            }
        }

        /*
        if ($skippedTransactions) {
            echo 'Skipped transactions (please enter manually):' . PHP_EOL;
            print_r($skippedTransactions);
        }
        */

        if ($this->exportCsv && !empty($transactionArray)) {
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

    public function exportAvanzaAccountTransactions(): void
    {
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

            // Exkludera allt som inte hör till ett specifikt värdepapper. Hanteras i exportFees.
            if (!empty($transaction->isin)) {
                continue;
            }

            if ($transaction->rawAmount === null) {
                Logger::getInstance()->addWarning('Missing amount for transaction for ' . $transaction->getDateString() . ' ' . $transaction->name . ' ' . $transaction->isin);
                continue;
            }

            switch ($transaction->type) {
                case TransactionType::DEPOSIT:
                    $type = 'Deposit';
                    break;
                case TransactionType::WITHDRAWAL:
                    $type = 'Removal';
                    break;
                case TransactionType::FEE:
                    if ($transaction->rawAmount < 0) {
                        $type = 'Fees';
                    } else {
                        $type = 'Fees Refund';
                    }
                    break;
                case TransactionType::INTEREST:
                    if ($transaction->rawAmount > 0) {
                        $type = 'Interest';
                    } else {
                        $type = 'Interest Charge';
                    }
                    break;
                case TransactionType::TAX:
                    if ($transaction->rawAmount < 0) {
                        $type = 'Taxes';
                    } else {
                        $type = 'Tax Refund';
                    }
                    break;
                case TransactionType::RETURNED_FOREIGN_WITHHOLDING_TAX:
                    $type = 'Tax Refund';
                    break;
                default:
                    $type = ucfirst($transaction->getTypeName());
            }

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
                $this->replaceDotWithComma(abs($transaction->rawAmount)),
                $transaction->currency,
            ];
        }

        if ($this->exportCsv && !empty($transactionArray)) {
            Exporter::exportToCsv(
                [
                    'Date',
                    'Cash Account',
                    'Securities Account',
                    'Note',
                    'Type',
                    'Value',
                    'Transaction Currency',
                ],
                $transactionArray,
                'avanza_account_transactions',
                ';'
            );
        }
    }

    public function exportAvanzaFees(): void
    {
        $isinGroupedTickers = [];
        foreach ($this->tickers as $ticker) {
            $isinGroupedTickers[$ticker->isin][] = $ticker->toArray();
        }

        $transactionArray = [];
        foreach ($this->transactions as $transaction) {
            if (!in_array($transaction->type, [
                TransactionType::FEE,
                TransactionType::FOREIGN_WITHHOLDING_TAX,
            ])) {
                continue;
            }

            switch ($transaction->type) {
                case TransactionType::FEE:
                    if ($transaction->rawAmount < 0) {
                        $type = 'Fees';
                    } else {
                        $type = 'Fees Refund';
                    }
                    break;
                case TransactionType::FOREIGN_WITHHOLDING_TAX:
                    $type = 'Taxes';
                    break;
                default:
                    $type = ucfirst($transaction->getTypeName());
            }

            // Allt som inte är kopplat till ett värdepapper hanteras i exportAccountTransactionsToPP.
            if (empty($transaction->isin)) {
                continue;
            }

            $currencies = $isinGroupedTickers[$transaction->isin];
            foreach ($currencies as $currency) {
                if (empty($transaction->rawAmount)) {
                    $note = 'Suspicious fee: ' . $transaction->getDateString() . ' ' . $transaction->name . ' ' . $transaction->isin . PHP_EOL;
                    continue;
                }

                $value = abs($transaction->rawAmount);
                $grossAmount = abs($transaction->rawAmount);
                $exchangeRate = 1;

                $name = $transaction->name;
                if (empty($name)) {
                    $name = $transaction->description;
                }
                if (!empty($note)) {
                    $name .= ' - ' . $note;
                }

                $transactionArray[] = [
                    $transaction->getDateString(),
                    ucfirst(strtolower($transaction->getBankName())) . ' SEK',
                    ucfirst(strtolower($transaction->getBankName())),
                    $name,
                    $transaction->isin,
                    $type,

                    $this->replaceDotWithComma($value), // Value
                    'SEK', // Transaction Currency

                    $this->replaceDotWithComma($grossAmount), // Gross Amount
                    $currency['currency'], // Currency Gross Amount

                    $exchangeRate
                ];
            }
        }

        if ($this->exportCsv && !empty($transactionArray)) {
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
            $isinGroupedTickers[$ticker->isin][] = $ticker->toArray();
        }

        $transactionArray = [];
        foreach ($this->transactions as $transaction) {
            if ($transaction->type !== TransactionType::DIVIDEND) {
                continue;
            }

            if ($transaction->rawQuantity === null || $transaction->rawAmount === null) {
                // TODO: logger
                continue;
            }

            $note = null;
            $currencies = $isinGroupedTickers[$transaction->isin];
            foreach ($currencies as $currency) {
                if ($transaction->rawAmount < 0) {
                    $note = 'Suspicious dividend: ' . $transaction->getDateString() . ' ' . $transaction->name . ' ' . $transaction->isin . PHP_EOL;
                }

                $value = abs($transaction->rawAmount);

                $exchangeRate = 1;

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
                    $this->replaceDotWithComma($value),
                    $this->replaceDotWithComma($value),
                    abs($transaction->rawQuantity),
                    $transactionCurrency,
                    $currencyGrossAmount,
                    $this->replaceDotWithComma($exchangeRate)
                ];
            }
        }

        if ($this->exportCsv && !empty($transactionArray)) {
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
            $isinGroupedTickers[$ticker->isin][] = $ticker->toArray();
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

            if ($transaction->isin === null) {
                // TODO: logger
                continue;
            }

            if ($transaction->rawQuantity === null) {
                // TODO: logger
                continue;
            }

            if ($transaction->rawPrice === null || $transaction->rawAmount === null) {
                // TODO: logger
                continue;
            }

            $currencies = $isinGroupedTickers[$transaction->isin];
            foreach ($currencies as $currency) {
                $value = abs($transaction->rawAmount);
                $grossAmount = abs($transaction->rawAmount);

                if ($transaction->commission === null) {
                    $commission = 0;
                } else {
                    $commission = abs($transaction->commission);
                }

                $exchangeRate = 1;
                if ($currency['currency'] !== 'SEK') {
                    $exchangeRate = ($value - $commission) / (abs($transaction->rawQuantity) * abs($transaction->rawPrice));
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
                    $this->replaceDotWithComma($value),
                    $this->replaceDotWithComma($grossAmount),
                    $this->replaceDotWithComma(abs($transaction->rawQuantity)),
                    $transactionCurrency,
                    $currencyGrossAmount,
                    $this->replaceDotWithComma($exchangeRate),
                    $this->replaceDotWithComma($commission)
                ];
            }
        }

        if ($skippedTransactions) {
            // TODO: logger
            echo 'Skipped transactions (please enter manually):' . PHP_EOL;
            print_r($skippedTransactions);
        }

        if ($this->exportCsv && !empty($transactionArray)) {
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

            // Exkludera allt som inte hör till ett specifikt värdepapper. Hanteras i exportFees.
            if (!empty($transaction->isin)) {
                continue;
            }

            if ($transaction->rawAmount === null) {
                // TODO: logger
                continue;
            }

            switch ($transaction->type) {
                case TransactionType::DEPOSIT:
                    $type = 'Deposit';
                    break;
                case TransactionType::WITHDRAWAL:
                    $type = 'Removal';
                    break;
                case TransactionType::FEE:
                    if ($transaction->rawAmount < 0) {
                        $type = 'Fees';
                    } else {
                        $type = 'Fees Refund';
                    }
                    break;
                case TransactionType::INTEREST:
                    if ($transaction->rawAmount > 0) {
                        $type = 'Interest';
                    } else {
                        $type = 'Interest Charge';
                    }
                    break;
                case TransactionType::TAX:
                    if ($transaction->rawAmount < 0) {
                        $type = 'Taxes';
                    } else {
                        $type = 'Tax Refund';
                    }
                    break;
                case TransactionType::RETURNED_FOREIGN_WITHHOLDING_TAX:
                    $type = 'Tax Refund';
                    break;
                default:
                    $type = ucfirst($transaction->getTypeName());
            }

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
                $this->replaceDotWithComma(abs($transaction->rawAmount)),
                $transaction->currency,
            ];
        }

        if ($this->exportCsv && !empty($transactionArray)) {
            Exporter::exportToCsv(
                [
                    'Date',
                    'Cash Account',
                    'Securities Account',
                    'Note',
                    'Type',
                    'Value',
                    'Transaction Currency',
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
            $isinGroupedTickers[$ticker->isin][] = $ticker->toArray();
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
            switch ($transaction->type) {
                case TransactionType::FOREIGN_WITHHOLDING_TAX:
                    $type = 'Taxes';

                    if ($transaction->rawAmount > 0 && !empty($transaction->isin)) {
                        $note = 'Suspicious foreign withholding tax: ' . $transaction->getDateString() . ' ' . $transaction->name . ' ' . $transaction->isin . PHP_EOL;
                    }

                    break;
                case TransactionType::FEE:
                    $type = ($transaction->rawAmount < 0) ? 'Fees' : 'Fees Refund';
                    break;
                default:
                    $type = ucfirst($transaction->getTypeName());
            }

            // Allt som inte är kopplat till ett värdepapper hanteras i exportAccountTransactionsToPP.
            if (empty($transaction->isin)) {
                continue;
            }

            $currencies = $isinGroupedTickers[$transaction->isin] ?? [];
            foreach ($currencies as $currency) {
                if (empty($transaction->rawAmount)) {
                    $note = 'Suspicious fee: ' . $transaction->getDateString() . ' ' . $transaction->name . ' ' . $transaction->isin . PHP_EOL;
                    continue;
                }

                $value = abs($transaction->rawAmount);
                $grossAmount = abs($transaction->rawAmount);
                $exchangeRate = 1;

                $name = $transaction->name;
                if (empty($name)) {
                    $name = $transaction->description;
                }
                if (!empty($note)) {
                    $name .= ' - ' . $note;
                }

                $transactionArray[] = [
                    $transaction->getDateString(),
                    ucfirst(strtolower($transaction->getBankName())) . ' SEK',
                    ucfirst(strtolower($transaction->getBankName())),
                    $name,
                    $transaction->isin,
                    $type,
                    $this->replaceDotWithComma($value), // Value
                    'SEK', // Transaction Currency
                    $this->replaceDotWithComma($grossAmount), // Gross Amount
                    $currency['currency'], // Currency Gross Amount
                    $this->replaceDotWithComma($exchangeRate)
                ];
            }
        }

        if ($this->exportCsv && !empty($transactionArray)) {
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

    private function replaceDotWithComma(string|float|int $value): string
    {
        return str_replace('.', ',', strval($value));
    }

    protected function getCurrencyByIsin(string $isin): string
    {
        return match(true) {
            str_starts_with($isin, 'SE') => 'SEK',
            str_starts_with($isin, 'FI') => 'EUR',
            str_starts_with($isin, 'US') => 'USD',
            str_starts_with($isin, 'NO') => 'NOK',
            str_starts_with($isin, 'DK') => 'DKK',
            str_starts_with($isin, 'DE') => 'EUR',
            str_starts_with($isin, 'GB') => 'GBP',
            str_starts_with($isin, 'CA') => 'CAD',
            default => 'SEK'
        };
    }
}
