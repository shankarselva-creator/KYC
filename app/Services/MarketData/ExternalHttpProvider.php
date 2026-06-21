<?php

namespace App\Services\MarketData;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Polls a remote HTTP JSON provider for live quotes.
 *
 * The payload mapping is configured in config/markets.php under "external" so a
 * range of vendors can be supported without code changes.
 */
class ExternalHttpProvider implements MarketDataProvider
{
    public function fetch(Collection $instruments): array
    {
        $config = config('markets.external');

        if (empty($config['url'])) {
            throw new RuntimeException('MARKET_DATA_API_URL is not configured for the external provider.');
        }

        $symbols = $instruments->pluck('symbol')->all();

        $request = Http::timeout((int) ($config['timeout'] ?? 8))
            ->acceptJson();

        $query = array_merge($config['query'] ?? [], [
            'symbols' => implode(',', $symbols),
        ]);

        // Apply authentication (header or query based).
        $auth = $config['auth'] ?? [];
        $key = $config['api_key'] ?? '';
        if ($key !== '' && ($auth['mode'] ?? 'header') === 'header') {
            $value = str_replace('{key}', $key, $auth['value'] ?? '{key}');
            $request = $request->withHeaders([$auth['name'] ?? 'Authorization' => $value]);
        } elseif ($key !== '' && ($auth['mode'] ?? null) === 'query') {
            $query[$auth['name'] ?? 'api_key'] = $key;
        }

        $response = $request->get($config['url'], $query);

        if (! $response->successful()) {
            Log::warning('Market data provider request failed', [
                'status' => $response->status(),
            ]);
            throw new RuntimeException("Market data provider returned HTTP {$response->status()}.");
        }

        $rows = $config['response_path']
            ? Arr::get($response->json(), $config['response_path'], [])
            : $response->json();

        return $this->normalise($rows ?? [], $config['map']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, string>  $map
     * @return array<string, array{symbol: string, bid: float, ask: float}>
     */
    private function normalise(array $rows, array $map): array
    {
        $out = [];

        foreach ($rows as $row) {
            $symbol = Arr::get($row, $map['symbol']);
            $bid = Arr::get($row, $map['bid']);
            $ask = Arr::get($row, $map['ask']);

            if ($symbol === null || $bid === null || $ask === null) {
                continue;
            }

            $out[$symbol] = [
                'symbol' => (string) $symbol,
                'bid'    => (float) $bid,
                'ask'    => (float) $ask,
            ];
        }

        return $out;
    }
}
