<?php

namespace Modules\WorkoutPlans\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\WorkoutPlans\Models\WorkoutDay;
use Modules\WorkoutPlans\Models\WorkoutPlan;

/**
 * @extends Factory<WorkoutDay>
 */
class WorkoutDayFactory extends Factory
{
    protected $model = WorkoutDay::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        static $order = 0;

        return [
            'workout_plan_id' => WorkoutPlan::factory(),
            'day_number' => fake()->numberBetween(1, 7),
            'name' => fake()->randomElement(['Upper body', 'Lower body', 'Push', 'Pull', 'Legs', 'Full body', 'Rest']),
            'order_index' => $order++,
        ];
    }
}
