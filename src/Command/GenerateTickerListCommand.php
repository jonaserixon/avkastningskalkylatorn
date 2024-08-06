<?php declare(strict_types=1);

namespace Avk\Command;

use Avk\DataStructure\FinancialAsset;
use Avk\Service\API\Eod\EodWrapper;
use Avk\Service\Transaction\TransactionLoader;
use Avk\View\TextColorizer;
use Exception;

class GenerateTickerListCommand extends CommandBase
{
    public function execute(): void
    {
        $transactionLoader = new TransactionLoader();

        $assets = $transactionLoader->getFinancialAssets($transactionLoader->getTransactions());
        $this->tickerPicker($assets);
    }

    /**
     * @param FinancialAsset[] $assets
     */
    private function tickerPicker(array $assets): void
    {
        $eodApi = new EodWrapper();

        $data = [];
        $filePath = ROOT_PATH . '/resources/tmp/tickers_TEST.json';
        if (file_exists($filePath) && filesize($filePath) > 0) {
            $jsonData = file_get_contents($filePath);

            if ($jsonData) {
                $data = json_decode($jsonData, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("JSON decode error: " . json_last_error_msg());
                }
            }
        }
       
        foreach ($assets as $asset) {
            echo $asset->name . ", ISIN: " . $asset->isin . PHP_EOL;
            $tickerSearch = $eodApi->searchForTickers($asset->isin);

            if (empty($tickerSearch)) {
                $assetNames = explode(' ', $asset->name);
                $tickerSearch = $eodApi->searchForTickers($assetNames[0] . ' ' . $assetNames[1]);
            }

            $tickerResult = [];
            $codeExchange = null;
            foreach ($tickerSearch as $row) {
                $tickerResult[] = $row->Code . '.' . $row->Exchange . ' - ' . $row->Name . ' (' . $row->Currency . ')';
            }

            $selectedTicker = null;
            if (count($tickerSearch) > 1) {
                foreach ($tickerSearch as $index => $ticker) {
                    echo TextColorizer::colorText(intval($index) + 1, 'blue') . ". Ticker: " . TextColorizer::colorText($ticker->Code . '.' . $ticker->Exchange, 'cyan') . " ({$ticker->Name}) - {$ticker->Currency}" . PHP_EOL;
                }

                echo "Välj en ticker genom att ange numret eller 0 för att skippa: ";
                $handle = fopen("php://stdin", "r");
                if ($handle === false) {
                    throw new Exception("Failed to open stdin.");
                }
                $choice = intval(fgets($handle));

                if ($choice === 0) {
                    $selectedTicker = null;
                } elseif ($choice > 0 && $choice <= count($tickerSearch)) {
                    $selectedTicker = $tickerSearch[$choice - 1];
                    $codeExchange = $selectedTicker->Code . '.' . $selectedTicker->Exchange;
                    echo "Du har valt ticker: '{$tickerResult[$choice - 1]}'\n";
                } else {
                    echo "Ogiltigt val, försök igen.\n";
                }
            } elseif (count($tickerSearch) === 1) {
                $selectedTicker = $tickerSearch[0];
                $codeExchange = $selectedTicker->Code . '.' . $selectedTicker->Exchange;
            } else {
                echo TextColorizer::colorText("Inga tickers hittades för denna ISIN", 'red') . PHP_EOL;
            }

            $data[] = [
                'isin' => $asset->isin,
                'ticker' => $codeExchange,
                'currency' => $selectedTicker->Currency ?? null,
                'name' => $asset->name
            ];

            echo "-----------------\n";

            $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON encode error: " . json_last_error_msg());
            }

            $result = file_put_contents($filePath, $jsonData);
            if ($result === false) {
                throw new Exception("Failed to write to file: {$filePath}");
            }
        }
    }
}
