<?php declare(strict_types=1);

namespace src\Service\API\Eod;

use Exception;
use stdClass;

class EodWrapper
{
    private string $apiToken;

    private const API_URL = 'https://eodhistoricaldata.com/api';

    public function __construct()
    {
        $apiToken = getenv('EOD_API_TOKEN');

        if (empty($apiToken)) {
            throw new Exception("EOD API is missing API token");
        }

        $this->apiToken = $apiToken;
    }

    /**
     * @param string $value Search value
     * @return stdClass[]
     */
    public function searchForTickers(string $value): array
    {
        $url = "/search/{$value}?api_token={$this->apiToken}&fmt=json";

        $data = $this->getRequest($url);

        return $data;
    }

    /**
     * @return stdClass[]
     */
    public function getHistoricalPricesByTicker(string $ticker, ?string $dateFrom, ?string $dateTo): array
    {
        $url = "/eod/{$ticker}?api_token={$this->apiToken}&fmt=json";

        if ($dateFrom) {
            $url .= "&from={$dateFrom}";
        }

        if ($dateTo) {
            $url .= "&to={$dateTo}";
        }

        $data = $this->getRequest($url);

        return $data;
    }

    // TODO: enable multiple http parallel requests

    /**
     * @return stdClass[]
     */
    private function getRequest(string $url): array
    {
        $url = self::API_URL . $url;

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($curl);
        // $info = curl_getinfo($curl);

        curl_close($curl);

        if (is_bool($response)) {
            return [];
            // throw new Exception("Failed to get data from EOD API");
        }

        $response = json_decode($response);
        if (!is_array($response)) {
            return [];
            // throw new Exception("Failed to decode JSON from EOD API - {$url}");
        }

        return $response;
    }
}
