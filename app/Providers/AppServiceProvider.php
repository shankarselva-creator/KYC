<?php

namespace App\Providers;

use App\Services\MarketData\ExternalHttpProvider;
use App\Services\MarketData\MarketDataProvider;
use App\Services\MarketData\SimulatedProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MarketDataProvider::class, function () {
            return match (config('markets.driver')) {
                'external' => new ExternalHttpProvider(),
                default    => new SimulatedProvider(),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
