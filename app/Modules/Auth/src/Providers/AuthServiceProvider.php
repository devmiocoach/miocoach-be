<?php

namespace App\Modules\Auth\Providers;

use App\Modules\Auth\Http\Middleware\JwtAuthenticate;
use App\Modules\Auth\Http\Middleware\RequireVerified;
use App\Modules\Auth\Services\AccountLockoutService;
use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Auth\Services\JwtService;
use App\Modules\Auth\Services\RateLimiterService;
use App\Modules\Auth\Services\RefreshTokenService;
use App\Modules\Auth\Services\TokenBlacklistService;
use Illuminate\Cache\RateLimiter;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(JwtService::class);
        $this->app->singleton(RefreshTokenService::class);
        $this->app->singleton(AuditLogService::class);
        $this->app->singleton(TokenBlacklistService::class);
        $this->app->singleton(AccountLockoutService::class);

        $this->app->singleton(RateLimiterService::class, function ($app) {
            return new RateLimiterService($app->make(RateLimiter::class));
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('jwt.auth', JwtAuthenticate::class);
        $router->aliasMiddleware('verified.email', RequireVerified::class);

        Route::middleware('api')->group(__DIR__ . '/../../routes/api.php');
    }
}
