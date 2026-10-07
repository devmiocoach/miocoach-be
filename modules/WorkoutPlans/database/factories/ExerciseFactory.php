<?php

namespace Modules\WorkoutPlans\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\WorkoutPlans\Enums\ExerciseCategory;
use Modules\WorkoutPlans\Enums\ExerciseDifficulty;
use Modules\WorkoutPlans\Models\Exercise;

/**
 * @extends Factory<Exercise>
 */
class ExerciseFactory extends Factory
{
    protected $model = Exercise::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'coach_id' => null,
            'name' => fake()->words(fake()->numberBetween(2, 4), true),
            'description' => fake()->sentence(),
            'instructions' => fake()->paragraph(),
            'category' => fake()->randomElement(ExerciseCategory::cases())->value,
            'difficulty' => fake()->randomElement(ExerciseDifficulty::cases())->value,
            'muscle_groups' => fake()->randomElements(['chest', 'back', 'shoulders', 'biceps', 'triceps', 'legs', 'glutes', 'core', 'calves'], fake()->numberBetween(1, 3)),
            'primary_muscles' => fake()->randomElements(['chest', 'back', 'shoulders', 'biceps', 'triceps', 'legs', 'glutes', 'core'], fake()->numberBetween(1, 2)),
            'secondary_muscles' => fake()->randomElements(['calves', 'forearms', 'hamstrings', 'quads', 'abs'], fake()->numberBetween(0, 2)),
            'equipment' => fake()->randomElements(['barbell', 'dumbbells', 'kettlebell', 'resistance_band', 'machine', 'bodyweight', 'cable'], fake()->numberBetween(1, 2)),
            'video_url' => null,
            'thumbnail_url' => null,
            'metadata' => null,
            'is_active' => true,
        ];
    }

    public function global(): static
    {
        return $this->state(['coach_id' => null]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function beginner(): static
    {
        return $this->state(['difficulty' => ExerciseDifficulty::Beginner->value]);
    }

    public function advanced(): static
    {
        return $this->state(['difficulty' => ExerciseDifficulty::Advanced->value]);
    }

    public function strength(): static
    {
        return $this->state(['category' => ExerciseCategory::Strength->value]);
    }
}
