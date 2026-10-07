<?php

namespace Modules\WorkoutPlans\Http\Requests;

use App\Rules\AllowedVideoHost;
use Illuminate\Foundation\Http\FormRequest;

class StoreExerciseRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'primaryMuscles' => ['nullable', 'array'],
            'primaryMuscles.*' => ['string', 'max:50'],
            'secondaryMuscles' => ['nullable', 'array'],
            'secondaryMuscles.*' => ['string', 'max:50'],
            'type' => ['nullable', 'string', 'max:50'],
            'videoUrl' => ['nullable', 'url', 'max:500', new AllowedVideoHost],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
