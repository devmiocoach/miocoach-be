<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\EmailVerificationNotificationController;
use Modules\Auth\Http\Controllers\EmailVerificationPromptController;
use Modules\Auth\Http\Controllers\ForgotPasswordController;
use Modules\Auth\Http\Controllers\LoginController;
use Modules\Auth\Http\Controllers\MagicLinkController;
use Modules\Auth\Http\Controllers\PasswordController;
use Modules\Auth\Http\Controllers\RegisterController;
use Modules\Auth\Http\Controllers\ReimpersonateController;
use Modules\Auth\Http\Controllers\ResetPasswordController;
use Modules\Auth\Http\Controllers\SocialiteController;
use Modules\Auth\Http\Controllers\VerifyEmailController;
use Modules\Auth\Http\Controllers\Web\TwoFactorController as WebTwoFactorController;
use Modules\Auth\Http\Controllers\Web\TwoFactorRecoveryController;

Route::prefix('auth')->group(function () {
    Route::middleware('guest')->group(function () {

        Route::get('login', [LoginController::class, 'create'])
            ->name('login');

        Route::post('login', [LoginController::class, 'store']);

        Route::get('register', [RegisterController::class, 'create'])
            ->name('register');

        Route::post('register', [RegisterController::class, 'store']);

        Route::get('forgot-password', [ForgotPasswordController::class, 'create'])
            ->name('password.request');

        Route::post('forgot-password', [ForgotPasswordController::class, 'store'])
            ->name('password.email');

        Route::get('reset-password/{token}', [ResetPasswordController::class, 'create'])
            ->name('password.reset');

        Route::post('reset-password', [ResetPasswordController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('password.store');

        Route::get('magic-link', [MagicLinkController::class, 'create'])
            ->name('magic-link.create');

        Route::post('magic-link', [MagicLinkController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('magic-link.store');
    });

    Route::middleware('auth')->group(function () {

        Route::any('logout', [LoginController::class, 'destroy'])
            ->name('logout');

        Route::get('verify-email', EmailVerificationPromptController::class)
            ->name('verification.notice');

        Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');

        Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('verification.send');

        Route::put('password', [PasswordController::class, 'update'])->name('password.update');

        Route::delete('socialite/{provider}', [SocialiteController::class, 'disconnect'])
            ->name('auth.socialite.disconnect');

        Route::post('impersonate/{userId}', ReimpersonateController::class)
            ->name('auth.impersonate.reimpersonate');

        // ─── Two-Factor Authentication (web/Inertia) ─────────────────────────
        Route::prefix('two-factor')->name('two-factor.')->group(function () {
            Route::post('enable', [WebTwoFactorController::class, 'enable'])
                ->name('enable');
            Route::get('confirm', [WebTwoFactorController::class, 'showConfirm'])
                ->name('confirm.show');
            Route::post('confirm', [WebTwoFactorController::class, 'confirm'])
                ->name('confirm');
            Route::delete('', [WebTwoFactorController::class, 'disable'])
                ->name('disable');
            Route::post('regenerate-codes', [WebTwoFactorController::class, 'regenerateCodes'])
                ->name('regenerate-codes');
            Route::get('regenerate-codes', [WebTwoFactorController::class, 'showRegeneratedCodes'])
                ->name('regenerate-codes.show');
        });
    });

    // ─── 2FA Challenge (during login — no auth middleware) ───────────────────
    Route::get('two-factor/challenge', [WebTwoFactorController::class, 'showChallenge'])
        ->name('two-factor.challenge.show')
        ->middleware('throttle:10,1');

    Route::post('two-factor/challenge', [WebTwoFactorController::class, 'challenge'])
        ->name('two-factor.challenge')
        ->middleware('throttle:10,1');

    // ─── 2FA Recovery (lost device — guest only) ─────────────────────────────
    Route::get('two-factor/recovery', [TwoFactorRecoveryController::class, 'showRequest'])
        ->name('two-factor.recovery.show')
        ->middleware('guest');

    Route::post('two-factor/recovery', [TwoFactorRecoveryController::class, 'sendRecoveryEmail'])
        ->name('two-factor.recovery.send')
        ->middleware(['guest', 'throttle:3,60']);

    Route::get('two-factor/recover/{token}', [TwoFactorRecoveryController::class, 'showRecover'])
        ->name('two-factor.recover')
        ->middleware('guest');

    Route::post('two-factor/recover/{token}', [TwoFactorRecoveryController::class, 'confirmRecover'])
        ->name('two-factor.recover.confirm')
        ->middleware('guest');

    /**
     * Socialite Routes
     *
     * These routes are placed outside the auth/guest middleware groups because:
     * - Guests can use them for social login/registration
     * - Authenticated users can use them to connect additional social providers
     */
    Route::get('socialite/{provider}', [SocialiteController::class, 'redirect'])
        ->name('auth.socialite.redirect');

    Route::get('socialite/{provider}/callback', [SocialiteController::class, 'callback'])
        ->name('auth.socialite.callback');

    /**
     * Magic Link Authentication
     *
     * Placed outside guest/auth groups because the user clicking the link
     * is not yet authenticated (they are in their email client).
     */
    Route::get('magic-link/{token}', [MagicLinkController::class, 'authenticate'])
        ->middleware('throttle:10,1')
        ->name('magic-link.authenticate');
});
