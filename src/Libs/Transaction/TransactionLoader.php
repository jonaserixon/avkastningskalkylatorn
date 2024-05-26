<?php

namespace src\Libs\Transaction;

use Exception;
use src\DataStructure\FinancialAsset;
use src\DataStructure\FinancialOverview;
use src\DataStructure\Transaction;
use src\Libs\FileManager\Importer\Avanza;
use src\Libs\FileManager\Importer\Nordnet;
use src\Libs\FileManager\Importer\StockPrice;

class TransactionLoader
{
    private ?string $filterBank;
    private ?string $filterIsin;
    private ?string $filterAsset;
    private ?string $filterDateFrom;
    private ?string $filterDateTo;
    private bool $filterCurrentHoldings;

    private TransactionParser $transactionParser;
    private StockPrice $stockPrice;
    public FinancialOverview $overview;

    public function __construct(
        // bool $exportCsv,
        ?string $filterBank,
        ?string $filterIsin,
        ?string $filterAsset,
        ?string $filterDateFrom,
        ?string $filterDateTo,
        bool $filterCurrentHoldings
    ) {
        // $this->exportCsv = $exportCsv;
        $this->filterBank = $filterBank;
        $this->filterIsin = $filterIsin;
        $this->filterAsset = $filterAsset;
        $this->filterDateFrom = $filterDateFrom;
        $this->filterDateTo = $filterDateTo;
        $this->filterCurrentHoldings = $filterCurrentHoldings;

        $this->overview = new FinancialOverview();
        $this->transactionParser = new TransactionParser($this->overview);
        $this->stockPrice = new StockPrice();
    }

    /**
     * @param Transaction[] $transactions
     * @return FinancialAsset[]
     */
    public function getFinancialAssets(array $transactions): array
    {
        $this->overview->firstTransactionDate = $transactions[0]->date;
        $this->overview->lastTransactionDate = $transactions[count($transactions) - 1]->date;

        $groupedTransactions = $this->transactionParser->groupTransactions($transactions);
        $assets = $this->transactionParser->summarizeTransactions($groupedTransactions);

        if (empty($assets)) {
            throw new Exception('No transaction file in csv format in the imports directory.');
        }

        // Sort assets by name for readability.
        usort($assets, function ($a, $b) {
            return strcasecmp($a->name, $b->name);
        });

        return $assets;
    }

    /**
     * Returns a list of sorted and possibly filtered transactions.
     * @return Transaction[]
     */
    public function getTransactions(): array
    {
        $transactions = array_merge(
            (new Avanza())->parseBankTransactions(),
            (new Nordnet())->parseBankTransactions()
        );

        $filters = [
            'bank' => $this->filterBank,
            'isin' => $this->filterIsin,
            'asset' => $this->filterAsset,
            'dateFrom' => $this->filterDateFrom,
            'dateTo' => $this->filterDateTo,
            'currentHoldings' => $this->filterCurrentHoldings
        ];

        foreach ($filters as $key => $value) {
            if ($value) {
                $value = mb_strtoupper($value);
                $transactions = array_filter($transactions, function ($transaction) use ($key, $value) {
                    if ($key === 'asset') {
                        // To support multiple assets
                        $assets = explode(',', $value);
                        foreach ($assets as $asset) {
                            if (str_contains(mb_strtoupper($transaction->name), trim($asset))) {
                                return true;
                            }
                        }

                        return false;
                    }

                    if ($key === 'dateFrom') {
                        return strtotime($transaction->date) >= strtotime($value);
                    }

                    if ($key === 'dateTo') {
                        return strtotime($transaction->date) <= strtotime($value);
                    }

                    if ($key === 'currentHoldings') {
                        $currentPricePerShare = $this->stockPrice->getCurrentPriceByIsin($transaction->isin);
                        return $currentPricePerShare !== null;
                    }

                    return mb_strtoupper($transaction->{$key}) === $value;
                });
            }
        }

        if (empty($transactions)) {
            throw new Exception('No transactions found');
        }

        // Sort transactions by date, bank and ISIN. (important for calculations and handling of transactions)
        usort($transactions, function ($a, $b) {
            $dateComparison = strtotime($a->date) <=> strtotime($b->date);
            if ($dateComparison !== 0) {
                return $dateComparison;
            }

            $bankComparison = strcmp($a->bank, $b->bank);
            if ($bankComparison !== 0) {
                return $bankComparison;
            }

            return strcmp($a->isin, $b->isin);
        });

        return $transactions;
    }
}
