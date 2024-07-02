<?php declare(strict_types=1);

namespace Avk\DataStructure;

readonly class TickerInfo
{
    public ?string $ticker;
    public string $isin;
    public string $name;
    public string $currency;

    public function __construct(?string $ticker, string $isin, string $name, string $currency)
    {
        $this->ticker = $ticker;
        $this->isin = $isin;
        $this->name = $name;
        $this->currency = $currency;
    }

    /**
     * @return mixed[]
     */
    public function toArray(): array
    {
        return [
            'ticker' => $this->ticker,
            'isin' => $this->isin,
            'name' => $this->name,
            'currency' => $this->currency,
        ];
    }
}
