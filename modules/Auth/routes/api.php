<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\Api\V1\AuthController;
use Modules\Auth\Http\Controllers\Api\V1\EmailVerificationController;
use Modules\Auth\Http\Controllers\Api\V1\InvitationController;
use Modules\Auth\Http\Controllers\Api\V1\PasswordController;
use Modules\Auth\Http\Controllers\Api\V1\TwoFactorController;
use Modules\Auth\Http\Controllers\Api\V1\TwoFactorVerifyController;

// JWT-based API — for mobile apps, third-party clients, etc.
Route::prefix('api/v1/auth')->name('auth.')->group(function () {

    // ─── Public endpoints ─────────────────────────────────────────────────────

    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login');

    Route::post('register/client', [AuthController::class, 'registerClient'])
        ->middleware('throttle:3,60')
        ->name('register.client');

    Route::post('register/coach', [AuthController::class, 'registerCoach'])
        ->middleware('throttle:3,60')
        ->name('register.coach');

    Route::post('two-factor/verify', TwoFactorVerifyController::class)
        ->middleware('throttle:10,1')
        ->name('two-factor.verify');

    Route::get('invite/{token}', [InvitationController::class, 'validate'])
        ->middleware('throttle:20,1')
        ->name('invite.validate');

    Route::post('invite/{token}/register', [InvitationController::class, 'register'])
        ->middleware('throttle:3,60')
        ->name('invite.register');

    Route::post('accept-invite/{token}', [InvitationController::class, 'register'])
        ->middleware('throttle:3,60')
        ->name('invite.accept');

    Route::post('forgot-password', [PasswordController::class, 'forgotPassword'])
        ->middleware('throttle:5,15')
        ->name('password.forgot');

    Route::post('reset-password', [PasswordController::class, 'resetPassword'])
        ->middleware('throttle:10,15')
        ->name('password.reset');

    Route::get('verify-email/{token}', [EmailVerificationController::class, 'verify'])
        ->middleware('throttle:6,1')
        ->name('verification.verify');

    // Refresh is public on purpose: the access token may already be expired, so
    // it cannot sit behind jwt.auth. The opaque refresh token (body or cookie)
    // is the credential and identifies the user.
    Route::post('refresh', [AuthController::class, 'refresh'])
        ->middleware('throttle:10,1')
        ->name('refresh');

    // ─── Authenticated endpoints (JWT access token required) ──────────────────

    Route::middleware('jwt.auth')->group(function () {

        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');

        Route::post('verify-email/resend', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:6,1')
            ->name('verification.resend');

        Route::middleware('verified.email')->group(function () {

            Route::prefix('two-factor')->name('two-factor.')->group(function () {
                Route::post('enable', [TwoFactorController::class, 'enable'])->name('enable');
                Route::post('confirm', [TwoFactorController::class, 'confirm'])->name('confirm');
                Route::delete('', [TwoFactorController::class, 'disable'])->name('disable');
                Route::post('regenerate-codes', [TwoFactorController::class, 'regenerateCodes'])->name('regenerate-codes');
            });
        });
    });
});
