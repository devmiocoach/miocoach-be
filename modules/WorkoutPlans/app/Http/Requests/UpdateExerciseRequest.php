<?php

namespace Modules\WorkoutPlans\Http\Requests;

use App\Rules\AllowedVideoHost;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExerciseRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'primaryMuscles' => ['sometimes', 'nullable', 'array'],
            'primaryMuscles.*' => ['string', 'max:50'],
            'secondaryMuscles' => ['sometimes', 'nullable', 'array'],
            'secondaryMuscles.*' => ['string', 'max:50'],
            'type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'videoUrl' => ['sometimes', 'nullable', 'url', 'max:500', new AllowedVideoHost],
            'instructions' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
