<?php

namespace Modules\WorkoutPlans\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateCoachPlanRequest extends CoachPlanRequest
{
    /**
     * On update an id may name an existing row, but only one belonging to the
     * plan being saved. A foreign id fails validation with a clean 422 rather
     * than reaching the writer (which would throw and roll back with a 500).
     * The route-model-bound plan is `{plan}`.
     *
     * @return array{day: array<int, mixed>, exercise: array<int, mixed>}
     */
    protected function childIdRules(): array
    {
        $planId = $this->route('plan')?->id;

        return [
            'day' => [
                'nullable', 'integer',
                Rule::exists('workout_days', 'id')->where('workout_plan_id', $planId),
            ],
            'exercise' => [
                'nullable', 'integer',
                Rule::exists('workout_exercises', 'id')->where('workout_plan_id', $planId),
            ],
        ];
    }
}
