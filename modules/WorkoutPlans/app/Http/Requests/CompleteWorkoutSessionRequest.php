<?php

namespace Modules\WorkoutPlans\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteWorkoutSessionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sets' => ['required', 'array'],
            'sets.*.exerciseId' => ['required', 'integer', 'exists:exercises,id'],
            'sets.*.setNumber' => ['required', 'integer', 'min:1'],
            'sets.*.repsDone' => ['nullable', 'integer', 'min:0'],
            'sets.*.weightKg' => ['nullable', 'numeric', 'min:0'],
            'sets.*.rirActual' => ['nullable', 'integer', 'min:0', 'max:10'],
            'sets.*.isCompleted' => ['boolean'],
            'durationMinutes' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
