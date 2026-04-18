<?php

use App\Modules\Auth\Http\Controllers\Api\V1\AuthController;
use App\Modules\Auth\Http\Controllers\Api\V1\EmailVerificationController;
use App\Modules\Auth\Http\Controllers\Api\V1\InvitationController;
use App\Modules\Auth\Http\Controllers\Api\V1\PasswordController;
use App\Modules\Auth\Http\Controllers\Api\V1\TwoFactorController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/auth')->name('auth.')->group(function () {

    // Login: HTTP-level fallback + rate limit applicativo interno (RateLimiterService + AccountLockoutService)
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login');

    // Registrazione client diretta
    Route::post('register/client', [AuthController::class, 'registerClient'])
        ->middleware('throttle:10,10')
        ->name('register.client');

    // Registrazione coach
    Route::post('register/coach', [AuthController::class, 'registerCoach'])
        ->middleware('throttle:10,10')
        ->name('register.coach');

    // Registrazione via invito coach (pubblica, token monouso)
    Route::get('invite/{token}', [InvitationController::class, 'validate'])
        ->middleware('throttle:20,1')
        ->name('invite.validate');

    Route::post('invite/{token}/register', [InvitationController::class, 'register'])
        ->middleware('throttle:10,10')
        ->name('invite.register');

    // Forgot password: 5 richieste ogni 15 minuti per IP (anti email flooding)
    Route::post('forgot-password', [PasswordController::class, 'forgotPassword'])
        ->middleware('throttle:5,15')
        ->name('password.forgot');

    // Reset password: 10 tentativi ogni 15 minuti per IP
    Route::post('reset-password', [PasswordController::class, 'resetPassword'])
        ->middleware('throttle:10,15')
        ->name('password.reset');

    // Verifica email (richiede firma URL)
    Route::get('verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // Rotte autenticate
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
        Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');

        // Verifica email
        Route::post('verify-email/resend', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:6,1')
            ->name('verification.resend');

        // 2FA
        Route::prefix('two-factor')->name('two-factor.')->group(function () {
            Route::post('enable', [TwoFactorController::class, 'enable'])->name('enable');
            Route::post('confirm', [TwoFactorController::class, 'confirm'])->name('confirm');
            Route::delete('', [TwoFactorController::class, 'disable'])->name('disable');
        });
    });
});
