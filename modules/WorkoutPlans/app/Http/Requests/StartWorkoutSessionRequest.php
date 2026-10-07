<?php

namespace Modules\WorkoutPlans\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartWorkoutSessionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'planId' => ['required', 'integer', 'exists:workout_plans,id'],
            'dayId' => ['required', 'integer', 'exists:workout_days,id'],
            'scheduledDate' => ['nullable', 'date'],
        ];
    }
}
