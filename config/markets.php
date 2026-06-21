<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Market Data Driver
    |--------------------------------------------------------------------------
    |
    | Determines where live quotes are sourced from:
    |   - "external"  : poll a remote HTTP JSON provider (production)
    |   - "simulated" : generate a seeded random walk locally (development/offline)
    |
    */

    'driver' => env('MARKET_DATA_DRIVER', 'simulated'),

    /*
    |--------------------------------------------------------------------------
    | External HTTP Provider
    |--------------------------------------------------------------------------
    |
    | Configuration for the live provider. The response mapping is intentionally
    | generic; tweak the "map" keys to match your vendor's payload. The provider
    | is expected to return a JSON array (or object under "response_path") where
    | each entry exposes a symbol, bid and ask.
    |
    */

    'external' => [
        'url'     => env('MARKET_DATA_API_URL'),
        'api_key' => env('MARKET_DATA_API_KEY'),
        'timeout' => (int) env('MARKET_DATA_TIMEOUT', 8),

        // How the API key is sent: "header" or "query".
        'auth' => [
            'mode'  => env('MARKET_DATA_AUTH_MODE', 'header'),
            'name'  => env('MARKET_DATA_AUTH_NAME', 'Authorization'),
            'value' => env('MARKET_DATA_AUTH_VALUE', 'Bearer {key}'),
        ],

        // Dot-path to the array of quotes inside the response (null = root array).
        'response_path' => env('MARKET_DATA_RESPONSE_PATH', null),

        // Field mapping from the provider payload to our quote shape.
        'map' => [
            'symbol' => 'symbol',
            'bid'    => 'bid',
            'ask'    => 'ask',
        ],

        // Optional query params merged into every request (e.g. symbol list).
        'query' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Simulated Provider
    |--------------------------------------------------------------------------
    */

    'simulated' => [
        // Maximum per-tick price movement, expressed in pips.
        'max_pip_move' => (float) env('MARKET_DATA_SIM_PIP_MOVE', 3.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Trading Defaults
    |--------------------------------------------------------------------------
    */

    'trading' => [
        'default_leverage' => (int) env('TRADING_DEFAULT_LEVERAGE', 100),
        'demo_balance'     => (float) env('TRADING_DEMO_BALANCE', 10000),
        'account_currency' => env('TRADING_ACCOUNT_CURRENCY', 'USD'),

        // Volume (lots) constraints for a single order.
        'min_volume'  => 0.01,
        'max_volume'  => 100.0,
        'volume_step' => 0.01,
    ],

];
