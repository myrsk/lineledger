<?php

namespace App\Services\Currency\Providers;

use App\Services\Currency\ExchangeRateProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/**
 * Free, key-less exchange rates via Frankfurter (frankfurter.dev). Rates are
 * weekday-only. The base URL is configurable so a paid provider can be swapped
 * in without touching callers.
 *
 * Speaks both API versions, chosen by the base URL's last path segment:
 *
 *   - v2 (the default): rates blended from many central banks, which covers
 *     currencies the ECB does not publish, such as the UAE dirham (AED).
 *     `GET /v2/rates?base=…&quotes=…[&date=…]` returns a flat list of
 *     `{date, base, quote, rate}` rows.
 *   - v1: ECB reference rates only. `GET /v1/{latest|date}?base=…&symbols=…`
 *     returns `{base, date, rates: {CODE: rate}}`. Kept for deployments that
 *     point FRANKFURTER_BASE_URL at a self-hosted v1 instance.
 */
class FrankfurterProvider implements ExchangeRateProvider
{
    public function __construct(
        private readonly string $baseUrl = 'https://api.frankfurter.dev/v2',
        private readonly int $timeout = 10,
    ) {}

    public function rates(string $base, array $quotes, ?CarbonImmutable $date = null): array
    {
        if ($quotes === []) {
            return [];
        }

        $base = mb_strtoupper($base);
        $quotes = array_map('mb_strtoupper', $quotes);

        return $this->isV1()
            ? $this->ratesV1($base, $quotes, $date)
            : $this->ratesV2($base, $quotes, $date);
    }

    private function isV1(): bool
    {
        return str_ends_with(rtrim($this->baseUrl, '/'), '/v1');
    }

    /**
     * @param  list<string>  $quotes
     * @return array<string, string>
     */
    private function ratesV2(string $base, array $quotes, ?CarbonImmutable $date): array
    {
        $query = [
            'base' => $base,
            'quotes' => implode(',', $quotes),
        ];

        if ($date !== null) {
            $query['date'] = $date->toDateString();
        }

        $rows = (array) Http::timeout($this->timeout)
            ->acceptJson()
            ->get(rtrim($this->baseUrl, '/').'/rates', $query)
            ->throw()
            ->json();

        $result = [];
        $seen = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['quote'], $row['rate'])) {
                continue;
            }

            $code = mb_strtoupper((string) $row['quote']);

            // Should several dates come back for one quote, keep the latest.
            if (isset($seen[$code]) && (string) ($row['date'] ?? '') < $seen[$code]) {
                continue;
            }

            $result[$code] = self::rateString($row['rate']);
            $seen[$code] = (string) ($row['date'] ?? '');
        }

        return $result;
    }

    /**
     * @param  list<string>  $quotes
     * @return array<string, string>
     */
    private function ratesV1(string $base, array $quotes, ?CarbonImmutable $date): array
    {
        $path = $date !== null ? $date->toDateString() : 'latest';

        $rates = (array) Http::timeout($this->timeout)
            ->acceptJson()
            ->get(rtrim($this->baseUrl, '/')."/{$path}", [
                'base' => $base,
                'symbols' => implode(',', $quotes),
            ])
            ->throw()
            ->json('rates', []);

        $result = [];

        foreach ($rates as $code => $rate) {
            $result[mb_strtoupper((string) $code)] = self::rateString($rate);
        }

        return $result;
    }

    /**
     * A JSON number as a plain decimal string. Very small floats would
     * otherwise stringify in exponent form ("1.0E-5"), which the
     * `decimal(18, 8)` rate columns and bcmath both reject.
     */
    private static function rateString(mixed $rate): string
    {
        if (is_float($rate)) {
            $fixed = rtrim(rtrim(sprintf('%.10F', $rate), '0'), '.');

            return $fixed === '' ? '0' : $fixed;
        }

        return (string) $rate;
    }
}
