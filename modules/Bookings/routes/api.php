<?php

use Illuminate\Support\Facades\Route;
use Modules\Bookings\Http\Controllers\Api\V1\AvailabilitySlotsController;
use Modules\Bookings\Http\Controllers\Api\V1\CoachBookingController;
use Modules\Bookings\Http\Controllers\Api\V1\GoogleCalendarAuthController;
use Modules\Bookings\Http\Controllers\Api\V1\PublicBookingController;

Route::prefix('api/v1')->group(function () {
    Route::middleware(['jwt.auth', 'verified.email', 'role:coach'])
        ->prefix('coaches/me')
        ->group(function () {
            Route::get('availability/slots', [AvailabilitySlotsController::class, 'index'])
                ->middleware('throttle:60,1')
                ->name('bookings.api.slots.index');

            Route::get('bookings', [CoachBookingController::class, 'index'])
                ->middleware('throttle:60,1')
                ->name('bookings.api.coach.index');

            Route::post('bookings', [CoachBookingController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('bookings.api.coach.store');

            Route::put('bookings/{booking}', [CoachBookingController::class, 'update'])
                ->middleware('throttle:30,1')
                ->name('bookings.api.coach.update');

            Route::delete('bookings/{booking}', [CoachBookingController::class, 'destroy'])
                ->middleware('throttle:20,1')
                ->name('bookings.api.coach.destroy');

            Route::prefix('calendar')->group(function () {
                Route::get('status', [GoogleCalendarAuthController::class, 'status'])
                    ->name('bookings.api.calendar.status');
                Route::get('auth', [GoogleCalendarAuthController::class, 'getAuthUrl'])
                    ->name('bookings.api.calendar.auth');
                Route::delete('disconnect', [GoogleCalendarAuthController::class, 'disconnect'])
                    ->name('bookings.api.calendar.disconnect');
            });
        });

    // Unauthenticated OAuth callback — Google redirects here
    Route::get('coaches/calendar/callback', [GoogleCalendarAuthController::class, 'handleCallback'])
        ->name('bookings.api.calendar.callback');

    Route::prefix('bookings/public')
        ->middleware('throttle:20,1')
        ->group(function () {
            Route::post('/', [PublicBookingController::class, 'store'])
                ->name('bookings.api.public.store');

            Route::get('/{token}/calendar.ics', [PublicBookingController::class, 'calendar'])
                ->name('bookings.api.public.calendar');

            Route::delete('/{token}', [PublicBookingController::class, 'cancel'])
                ->name('bookings.api.public.cancel');
        });
});
