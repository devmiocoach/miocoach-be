<?php

namespace Modules\WorkoutPlans\Providers;

use App\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\Gate;
use Modules\WorkoutPlans\Models\Exercise;
use Modules\WorkoutPlans\Models\WorkoutPlan;
use Modules\WorkoutPlans\Policies\ExercisePolicy;
use Modules\WorkoutPlans\Policies\WorkoutPlanPolicy;

class WorkoutPlansServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'WorkoutPlans';

    protected string $nameLower = 'workoutplans';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Gate::policy(WorkoutPlan::class, WorkoutPlanPolicy::class);
        Gate::policy(Exercise::class, ExercisePolicy::class);
    }
}
