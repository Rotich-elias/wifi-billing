<?php

namespace App\Providers;

use App\Contracts\RouterDriver;
use App\Services\MikrotikService;
use App\Services\Router\MockRouterDriver;
use App\Services\Router\RouterOsApiDriver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RouterDriver::class, function ($app) {
            if (config('router.driver') === 'routeros') {
                return new RouterOsApiDriver($app->make(MikrotikService::class));
            }

            return new MockRouterDriver;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('purchase', function (Request $request) {
            return [
                Limit::perMinute(20)->by('ip:'.$request->ip()),
                Limit::perMinutes(10, 3)->by('phone:'.preg_replace('/\D/', '', (string) $request->input('phone'))),
            ];
        });

        RateLimiter::for('voucher-check', function (Request $request) {
            return Limit::perMinute(10)->by('ip:'.$request->ip());
        });

        RateLimiter::for('mpesa-callback', function (Request $request) {
            return Limit::perMinute(120)->by('ip:'.$request->ip());
        });
    }
}
