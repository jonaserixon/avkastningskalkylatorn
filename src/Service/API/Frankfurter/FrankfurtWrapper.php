<?php

namespace src\Service\API\Frankfurter;
use stdClass;

class FrankfurtWrapper
{
    private const API_URL = 'https://api.frankfurter.app';

    public function getExchangeRateByCurrencyAndDate(string $currency, string $date): float
    {
        $url = "/{$date}?from={$currency}&to=SEK";

        $result = $this->getRequest($url);

        return $result->rates->SEK;
    }

    private function getRequest(string $url): stdClass
    {
        $url = self::API_URL . $url;

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($curl);
        // $info = curl_getinfo($curl);

        curl_close($curl);

        $response = json_decode($response);

        return $response;
    }
}
