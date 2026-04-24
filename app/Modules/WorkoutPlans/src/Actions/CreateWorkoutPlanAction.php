<?php

namespace App\Modules\WorkoutPlans\Actions;

use App\Modules\WorkoutPlans\Models\WorkoutPlan;
use Illuminate\Support\Arr;

class CreateWorkoutPlanAction
{
    public function execute(array $data, int $coachId): WorkoutPlan
    {
        $exercises = Arr::pull($data, 'exercises', []);

        $plan = WorkoutPlan::create(array_merge($data, ['coach_id' => $coachId]));

        if (!empty($exercises)) {
            $plan->exercises()->sync($this->prepareExercises($exercises));
        }

        return $plan->load('exercises');
    }

    private function prepareExercises(array $exercises): array
    {
        $synced = [];
        foreach ($exercises as $index => $ex) {
            $synced[$ex['exercise_id']] = array_merge(
                Arr::except($ex, ['exercise_id']),
                ['order' => $ex['order'] ?? $index]
            );
        }
        return $synced;
    }
}
