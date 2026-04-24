<?php

namespace App\Modules\WorkoutPlans\Actions;

use App\Modules\WorkoutPlans\Models\WorkoutPlan;

class DeleteWorkoutPlanAction
{
    public function execute(WorkoutPlan $plan): void
    {
        $plan->delete();
    }
}
