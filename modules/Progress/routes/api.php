<?php

use Illuminate\Support\Facades\Route;
use Modules\Progress\Http\Controllers\Api\V1\MeasurementController;
use Modules\Progress\Http\Controllers\Api\V1\MeasurementGoalController;
use Modules\Progress\Http\Controllers\Api\V1\ProgressPhotoController;

Route::prefix('api/v1')->middleware(['jwt.auth', 'verified.email', 'role:coach'])->group(function () {
    Route::prefix('coaches/me/clients/{client}')->group(function () {
        // Body measurements
        Route::get('measurements', [MeasurementController::class, 'index'])
            ->middleware('throttle:60,1')
            ->name('progress.api.measurements.index');

        Route::post('measurements', [MeasurementController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('progress.api.measurements.store');

        Route::put('measurements/{measurement}', [MeasurementController::class, 'update'])
            ->middleware('throttle:30,1')
            ->name('progress.api.measurements.update');

        Route::delete('measurements/{measurement}', [MeasurementController::class, 'destroy'])
            ->middleware('throttle:20,1')
            ->name('progress.api.measurements.destroy');

        Route::get('measurements/chart', [MeasurementController::class, 'chart'])
            ->middleware('throttle:60,1')
            ->name('progress.api.measurements.chart');

        // Measurement goals
        Route::get('measurement-goals', [MeasurementGoalController::class, 'index'])
            ->middleware('throttle:60,1')
            ->name('progress.api.goals.index');

        Route::put('measurement-goals/{metricType}', [MeasurementGoalController::class, 'upsert'])
            ->middleware('throttle:30,1')
            ->name('progress.api.goals.upsert');

        Route::delete('measurement-goals/{metricType}', [MeasurementGoalController::class, 'destroy'])
            ->middleware('throttle:20,1')
            ->name('progress.api.goals.destroy');

        // Progress photos
        Route::get('progress-photos', [ProgressPhotoController::class, 'index'])
            ->middleware('throttle:60,1')
            ->name('progress.api.photos.index');

        Route::post('progress-photos', [ProgressPhotoController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('progress.api.photos.store');

        Route::delete('progress-photos/{photo}', [ProgressPhotoController::class, 'destroy'])
            ->middleware('throttle:20,1')
            ->name('progress.api.photos.destroy');
    });
});
