<?php

namespace App\Modules\WorkoutPlans\Providers;

use App\Modules\WorkoutPlans\Models\WorkoutPlan;
use App\Modules\WorkoutPlans\Policies\WorkoutPlanPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class WorkoutPlansServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Il gruppo 'api' applica SubstituteBindings (necessario per route model binding)
        Route::middleware('api')->group(__DIR__ . '/../../routes/api.php');

        Gate::policy(WorkoutPlan::class, WorkoutPlanPolicy::class);
    }
}
