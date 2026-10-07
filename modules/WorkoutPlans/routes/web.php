<?php

use Illuminate\Support\Facades\Route;
use Modules\WorkoutPlans\Http\Controllers\Coach\ExerciseController;
use Modules\WorkoutPlans\Http\Controllers\Coach\PlanBuilderController;

Route::middleware(['auth', 'role:coach'])->prefix('coach')->name('coach.')->group(function () {
    Route::get('plans', [PlanBuilderController::class, 'index'])->name('plans.index');
    Route::get('plans/create', [PlanBuilderController::class, 'create'])->name('plans.create');
    Route::post('plans', [PlanBuilderController::class, 'store'])->name('plans.store');
    Route::get('plans/{plan}/edit', [PlanBuilderController::class, 'edit'])->name('plans.edit');
    Route::put('plans/{plan}', [PlanBuilderController::class, 'update'])->name('plans.update');
    Route::post('plans/{plan}/duplicate', [PlanBuilderController::class, 'duplicate'])->name('plans.duplicate');
    Route::delete('plans/{plan}', [PlanBuilderController::class, 'destroy'])->name('plans.destroy');

    Route::get('exercises', [ExerciseController::class, 'index'])->name('exercises.index');
    Route::post('exercises', [ExerciseController::class, 'store'])->name('exercises.store');
    Route::put('exercises/{exercise}', [ExerciseController::class, 'update'])->name('exercises.update');
    Route::delete('exercises/{exercise}', [ExerciseController::class, 'destroy'])->name('exercises.destroy');
});
