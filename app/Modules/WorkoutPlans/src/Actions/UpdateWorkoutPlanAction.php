<?php

namespace App\Modules\WorkoutPlans\Actions;

use App\Modules\WorkoutPlans\Models\WorkoutPlan;
use Illuminate\Support\Arr;

class UpdateWorkoutPlanAction
{
    public function execute(WorkoutPlan $plan, array $data): WorkoutPlan
    {
        $exercises = Arr::pull($data, 'exercises');

        $plan->update($data);

        if ($exercises !== null) {
            $synced = [];
            foreach ($exercises as $index => $ex) {
                $synced[$ex['exercise_id']] = array_merge(
                    Arr::except($ex, ['exercise_id']),
                    ['order' => $ex['order'] ?? $index]
                );
            }
            $plan->exercises()->sync($synced);
        }

        return $plan->load('exercises');
    }
}
