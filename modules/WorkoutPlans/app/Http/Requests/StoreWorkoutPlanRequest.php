<?php

namespace Modules\WorkoutPlans\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkoutPlanRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'isTemplate' => ['boolean'],
            'goal' => ['nullable', 'string', 'max:50'],
            'difficulty' => ['nullable', 'string', 'max:50'],
            'durationWeeks' => ['nullable', 'integer', 'min:1', 'max:52'],
            'sessionsPerWeek' => ['nullable', 'integer', 'min:1', 'max:7'],
            'days' => ['nullable', 'array', 'max:7'],
            'days.*.id' => ['prohibited'],
            'days.*.dayNumber' => ['required_with:days', 'integer', 'min:1', 'max:31'],
            'days.*.name' => ['nullable', 'string', 'max:255'],
            'days.*.exercises' => ['nullable', 'array', 'max:50'],
            'days.*.exercises.*.id' => ['prohibited'],
            'days.*.exercises.*.exerciseId' => ['required_with:days.*.exercises', 'integer', 'exists:exercises,id'],
            'days.*.exercises.*.sets' => ['nullable', 'integer', 'min:1', 'max:20'],
            'days.*.exercises.*.repsTarget' => ['nullable', 'integer', 'min:1', 'max:100'],
            'days.*.exercises.*.weightKg' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'days.*.exercises.*.rirTarget' => ['nullable', 'integer', 'min:0', 'max:10'],
            'days.*.exercises.*.restSeconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'days.*.exercises.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
