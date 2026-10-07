<?php

namespace Modules\WorkoutPlans\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\WorkoutPlans\Models\Exercise;
use Modules\WorkoutPlans\Models\WorkoutExercise;

/**
 * @extends Factory<WorkoutExercise>
 */
class WorkoutExerciseFactory extends Factory
{
    protected $model = WorkoutExercise::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'workout_plan_id' => null,
            'workout_day_id' => null,
            'exercise_id' => Exercise::factory(),
            'sets' => fake()->numberBetween(3, 5),
            'reps_target' => fake()->numberBetween(6, 12),
            'weight_kg' => fake()->randomFloat(2, 20, 120),
            'rir_target' => fake()->numberBetween(0, 4),
            'rest_seconds' => fake()->randomElement([60, 90, 120]),
            'notes' => null,
            'order_index' => 0,
        ];
    }
}
