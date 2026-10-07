<?php

use Illuminate\Support\Facades\Route;
use Modules\Progress\Http\Controllers\Api\V1\MeasurementController;
use Modules\Progress\Http\Controllers\Api\V1\MeasurementGoalController;
use Modules\Progress\Http\Controllers\Api\V1\ProgressPhotoController;

Route::middleware(['web', 'auth', 'verified', 'role_guard:coach', 'throttle:60,1'])
    ->prefix('coach/clients/{client}/progress')
    ->name('coach.clients.progress.')
    ->group(function () {
        Route::get('measurements', [MeasurementController::class, 'index'])->name('measurements.index');
        Route::post('measurements', [MeasurementController::class, 'store'])->name('measurements.store');
        Route::put('measurements/{measurement}', [MeasurementController::class, 'update'])->name('measurements.update');
        Route::delete('measurements/{measurement}', [MeasurementController::class, 'destroy'])->name('measurements.destroy');
        Route::get('measurements/chart', [MeasurementController::class, 'chart'])->name('measurements.chart');

        Route::get('goals', [MeasurementGoalController::class, 'index'])->name('goals.index');
        Route::put('goals/{metricType}', [MeasurementGoalController::class, 'upsert'])->name('goals.upsert');
        Route::delete('goals/{metricType}', [MeasurementGoalController::class, 'destroy'])->name('goals.destroy');

        Route::get('photos', [ProgressPhotoController::class, 'index'])->name('photos.index');
        Route::post('photos', [ProgressPhotoController::class, 'store'])->name('photos.store');
        Route::delete('photos/{photo}', [ProgressPhotoController::class, 'destroy'])->name('photos.destroy');
    });
