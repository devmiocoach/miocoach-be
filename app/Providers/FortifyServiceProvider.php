<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Disabilita la registrazione automatica delle route web di Fortify
        // (login, register, reset-password, ecc.) che sono gestite dai moduli
        // Auth e Users con endpoint API dedicati (/api/v1/auth/*).
        // Le action Fortify per 2FA (EnableTwoFactorAuthentication, ConfirmTwoFactorAuthentication,
        // DisableTwoFactorAuthentication) vengono usate direttamente nelle nostre Action,
        // senza bisogno di esporre le route web di Fortify.
        Fortify::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     *
     * Fortify è configurato per gestire solo il 2FA TOTP via API.
     * Login, registrazione, reset password e profilo utente sono gestiti
     * dai moduli Auth e Users con endpoint API dedicati (/api/v1/auth/*).
     */
    public function boot(): void
    {
        // Rate limiter per il two-factor challenge (Fortify lo usa internamente
        // nelle action EnableTwoFactorAuthentication / ConfirmTwoFactorAuthentication).
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
