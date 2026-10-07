<?php

use Illuminate\Support\Facades\Route;
use Modules\WorkoutPlans\Http\Controllers\Api\V1\ClientWorkoutController;
use Modules\WorkoutPlans\Http\Controllers\Api\V1\ExerciseController;
use Modules\WorkoutPlans\Http\Controllers\Api\V1\WorkoutPlanController;
use Modules\WorkoutPlans\Http\Controllers\Api\V1\WorkoutSessionController;

Route::prefix('api/v1')->middleware(['jwt.auth', 'verified.email'])->group(function () {

    // Exercise library — readable by any authenticated user
    Route::get('exercises', [ExerciseController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('workoutplans.api.exercises.index');

    // Coach-only exercise management
    Route::prefix('coaches/me')->middleware('role:coach')->group(function () {
        Route::post('exercises', [ExerciseController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('workoutplans.api.exercises.store');

        Route::put('exercises/{exercise}', [ExerciseController::class, 'update'])
            ->middleware('throttle:30,1')
            ->name('workoutplans.api.exercises.update');

        Route::delete('exercises/{exercise}', [ExerciseController::class, 'destroy'])
            ->middleware('throttle:20,1')
            ->name('workoutplans.api.exercises.destroy');

        // Workout plan management
        Route::post('workout-plans', [WorkoutPlanController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('workoutplans.api.plans.store');

        Route::get('workout-plans', [WorkoutPlanController::class, 'index'])
            ->middleware('throttle:60,1')
            ->name('workoutplans.api.plans.index');
    });

    // Workout plan detail + actions — coach-scoped, ownership checked in controller
    Route::get('workout-plans/{workoutPlan}', [WorkoutPlanController::class, 'show'])
        ->middleware('throttle:60,1')
        ->name('workoutplans.api.plans.show');

    Route::put('workout-plans/{workoutPlan}', [WorkoutPlanController::class, 'update'])
        ->middleware(['role:coach', 'throttle:30,1'])
        ->name('workoutplans.api.plans.update');

    Route::delete('workout-plans/{workoutPlan}', [WorkoutPlanController::class, 'destroy'])
        ->middleware(['role:coach', 'throttle:20,1'])
        ->name('workoutplans.api.plans.destroy');

    Route::post('workout-plans/{workoutPlan}/duplicate', [WorkoutPlanController::class, 'duplicate'])
        ->middleware(['role:coach', 'throttle:10,1'])
        ->name('workoutplans.api.plans.duplicate');

    Route::post('workout-plans/{workoutPlan}/assign', [WorkoutPlanController::class, 'assign'])
        ->middleware(['role:coach', 'throttle:20,1'])
        ->name('workoutplans.api.plans.assign');

    // Athlete (client) self-service — scoped to the authenticated client (JWT)
    Route::prefix('clients/me')->middleware('role:client')->group(function () {
        // Assigned plans, session history, personal records (read)
        Route::get('workout-plans', [ClientWorkoutController::class, 'plans'])
            ->middleware('throttle:60,1')
            ->name('workoutplans.api.client.plans');

        Route::get('workout-sessions', [ClientWorkoutController::class, 'sessions'])
            ->middleware('throttle:60,1')
            ->name('workoutplans.api.client.sessions');

        Route::get('personal-records', [ClientWorkoutController::class, 'personalRecords'])
            ->middleware('throttle:60,1')
            ->name('workoutplans.api.client.personal-records');

        // Session lifecycle (logging a workout)
        Route::post('workout-sessions/start', [WorkoutSessionController::class, 'start'])
            ->middleware('throttle:30,1')
            ->name('workoutplans.api.sessions.start');

        Route::post('workout-sessions/{workoutSession}/complete', [WorkoutSessionController::class, 'complete'])
            ->middleware('throttle:20,1')
            ->name('workoutplans.api.sessions.complete');
    });
});
