<?php

use App\Modules\WorkoutPlans\Http\Controllers\Api\V1\ExerciseController;
use App\Modules\WorkoutPlans\Http\Controllers\Api\V1\WorkoutPlanController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware('auth:sanctum')->group(function () {
    Route::apiResource('workout-plans', WorkoutPlanController::class);
    Route::get('exercises', [ExerciseController::class, 'index'])->name('exercises.index');
    Route::get('exercises/{exercise}', [ExerciseController::class, 'show'])->name('exercises.show');
});
