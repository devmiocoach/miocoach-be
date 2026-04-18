<?php

namespace App\Modules\Auth\Providers;

use App\Modules\Auth\Services\AccountLockoutService;
use App\Modules\Auth\Services\RateLimiterService;
use App\Modules\Auth\Services\TokenBlacklistService;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TokenBlacklistService::class);
        $this->app->singleton(AccountLockoutService::class);

        $this->app->singleton(RateLimiterService::class, function ($app) {
            return new RateLimiterService($app->make(RateLimiter::class));
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        Route::middleware('api')->group(__DIR__ . '/../../routes/api.php');
    }
}
