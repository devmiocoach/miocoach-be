<?php

namespace Modules\WorkoutPlans\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkoutPlanRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required_with:days', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'isTemplate' => ['sometimes', 'boolean'],
            'isActive' => ['sometimes', 'boolean'],
            'goal' => ['sometimes', 'nullable', 'string', 'max:50'],
            'difficulty' => ['sometimes', 'nullable', 'string', 'max:50'],
            'durationWeeks' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:52'],
            'sessionsPerWeek' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:7'],
            'days' => ['sometimes', 'array', 'max:7'],
            'days.*.id' => [
                'nullable', 'integer',
                Rule::exists('workout_days', 'id')->where('workout_plan_id', $this->route('workoutPlan')?->id),
            ],
            'days.*.dayNumber' => ['required_with:days', 'integer', 'min:1', 'max:31'],
            'days.*.name' => ['nullable', 'string', 'max:255'],
            'days.*.exercises' => ['nullable', 'array', 'max:50'],
            'days.*.exercises.*.id' => [
                'nullable', 'integer',
                Rule::exists('workout_exercises', 'id')->where('workout_plan_id', $this->route('workoutPlan')?->id),
            ],
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
