<?php

namespace Modules\WorkoutPlans\Http\Requests;

class StoreCoachPlanRequest extends CoachPlanRequest
{
    /**
     * On create there is no existing row to reference. `prohibited` still
     * accepts a null (the builder sends `id: null` for uniform shape) but
     * rejects any real id, so a client cannot smuggle another plan's row id
     * into a brand-new plan.
     *
     * @return array{day: array<int, mixed>, exercise: array<int, mixed>}
     */
    protected function childIdRules(): array
    {
        return [
            'day' => ['prohibited'],
            'exercise' => ['prohibited'],
        ];
    }
}
