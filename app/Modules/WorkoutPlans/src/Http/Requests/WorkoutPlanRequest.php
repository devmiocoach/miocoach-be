<?php

namespace App\Modules\WorkoutPlans\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkoutPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'             => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string'],
            'goal'              => ['nullable', 'string', 'in:strength,hypertrophy,weight_loss,endurance,flexibility'],
            'duration_weeks'    => ['nullable', 'integer', 'min:1', 'max:52'],
            'sessions_per_week' => ['nullable', 'integer', 'min:1', 'max:7'],
            'difficulty'        => ['nullable', 'string', 'in:beginner,intermediate,advanced'],
            'status'            => ['nullable', 'string', 'in:draft,active,archived'],
            'client_id'         => ['nullable', 'integer', 'exists:clients,id'],
            'is_template'       => ['nullable', 'boolean'],
            'exercises'         => ['nullable', 'array'],
            'exercises.*.exercise_id'      => ['required', 'integer', 'exists:exercises,id'],
            'exercises.*.order'            => ['nullable', 'integer', 'min:0'],
            'exercises.*.sets'             => ['nullable', 'integer', 'min:1'],
            'exercises.*.reps'             => ['nullable', 'string'],
            'exercises.*.rest_seconds'     => ['nullable', 'string'],
            'exercises.*.weight_kg'        => ['nullable', 'numeric'],
            'exercises.*.duration_seconds' => ['nullable', 'string'],
            'exercises.*.notes'            => ['nullable', 'string'],
            'exercises.*.day_of_week'      => ['nullable', 'string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
        ];
    }
}
