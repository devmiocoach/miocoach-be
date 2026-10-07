<?php

use Illuminate\Support\Facades\Route;
use Modules\Checkins\Http\Controllers\Api\V1\CheckinFormController;
use Modules\Checkins\Http\Controllers\Api\V1\CheckinResponseController;
use Modules\Checkins\Http\Controllers\Api\V1\CoachCheckinController;

Route::prefix('api/v1')->middleware(['jwt.auth', 'verified.email'])->group(function () {

    Route::prefix('coaches/me')->middleware('role:coach')->group(function () {
        Route::get('checkin-forms', [CheckinFormController::class, 'index'])
            ->middleware('throttle:120,1')->name('checkins.api.checkin-forms.index');

        Route::post('checkin-forms', [CheckinFormController::class, 'store'])
            ->middleware('throttle:30,1')->name('checkins.api.checkin-forms.store');

        Route::put('checkin-forms/{checkinForm}', [CheckinFormController::class, 'update'])
            ->middleware('throttle:30,1')->name('checkins.api.checkin-forms.update');

        Route::post('checkin-forms/{checkinForm}/assign', [CheckinFormController::class, 'assign'])
            ->middleware('throttle:30,1')->name('checkins.api.checkin-forms.assign');

        Route::get('clients/{client}/checkins', [CoachCheckinController::class, 'index'])
            ->middleware('throttle:120,1')
            ->name('checkins.api.coaches.clients.checkins.index');
    });

    Route::post('checkin-forms/{checkinForm}/respond', [CheckinResponseController::class, 'respond'])
        ->middleware(['role:client', 'throttle:30,1'])
        ->name('checkins.api.checkin-forms.respond');
});
