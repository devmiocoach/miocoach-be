<?php

use App\Modules\Auth\Http\Controllers\Api\V1\AuthController;
use App\Modules\Auth\Http\Controllers\Api\V1\EmailVerificationController;
use App\Modules\Auth\Http\Controllers\Api\V1\InvitationController;
use App\Modules\Auth\Http\Controllers\Api\V1\PasswordController;
use App\Modules\Auth\Http\Controllers\Api\V1\TwoFactorController;
use App\Modules\Auth\Http\Controllers\Api\V1\TwoFactorVerifyController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/auth')->name('auth.')->group(function () {

    // ─── Public endpoints ─────────────────────────────────────────────────────

    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login');

    // Spec: 3 registrations per IP per hour
    Route::post('register/client', [AuthController::class, 'registerClient'])
        ->middleware('throttle:3,60')
        ->name('register.client');

    Route::post('register/coach', [AuthController::class, 'registerCoach'])
        ->middleware('throttle:3,60')
        ->name('register.coach');

    // 2FA login completion (public — uses tempToken for auth)
    Route::post('two-factor/verify', TwoFactorVerifyController::class)
        ->middleware('throttle:10,1')
        ->name('two-factor.verify');

    // Invitations (public, one-time token)
    Route::get('invite/{token}', [InvitationController::class, 'validate'])
        ->middleware('throttle:20,1')
        ->name('invite.validate');

    Route::post('invite/{token}/register', [InvitationController::class, 'register'])
        ->middleware('throttle:3,60')
        ->name('invite.register');

    // Password reset: 5 requests per 15 min per IP
    Route::post('forgot-password', [PasswordController::class, 'forgotPassword'])
        ->middleware('throttle:5,15')
        ->name('password.forgot');

    Route::post('reset-password', [PasswordController::class, 'resetPassword'])
        ->middleware('throttle:10,15')
        ->name('password.reset');

    // Email verification (JWT token in path, redirects to frontend)
    Route::get('verify-email/{token}', [EmailVerificationController::class, 'verify'])
        ->middleware('throttle:6,1')
        ->name('verification.verify');

    // ─── Authenticated endpoints (JWT access token required) ──────────────────

    Route::middleware('jwt.auth')->group(function () {

        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
        Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');

        // Resend verification (does not require verified)
        Route::post('verify-email/resend', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:6,1')
            ->name('verification.resend');

        // ─── Verified users only ─────────────────────────────────────────────

        Route::middleware('verified.email')->group(function () {

            Route::prefix('two-factor')->name('two-factor.')->group(function () {
                Route::post('enable', [TwoFactorController::class, 'enable'])->name('enable');
                Route::post('confirm', [TwoFactorController::class, 'confirm'])->name('confirm');
                Route::delete('', [TwoFactorController::class, 'disable'])->name('disable');
            });
        });
    });
});
