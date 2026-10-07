<?php

namespace Modules\WorkoutPlans\Http\Requests;

use App\Rules\AllowedVideoHost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\WorkoutPlans\Enums\ExerciseCategory;

class StoreCoachExerciseRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            // exercises.category is NOT NULL, so a missing type would 500 on insert.
            'type' => ['required', Rule::enum(ExerciseCategory::class)],
            'primaryMuscles' => ['nullable', 'array'],
            'primaryMuscles.*' => ['string', 'max:50'],
            'secondaryMuscles' => ['nullable', 'array'],
            'secondaryMuscles.*' => ['string', 'max:50'],
            'videoUrl' => ['nullable', 'url', 'max:500', new AllowedVideoHost],
        ];
    }
}
