<?php

namespace Modules\WorkoutPlans\Database\Factories;

use App\Modules\Users\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\WorkoutPlans\Models\WorkoutSession;

/**
 * @extends Factory<WorkoutSession>
 */
class WorkoutSessionFactory extends Factory
{
    protected $model = WorkoutSession::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-30 days', 'now');

        return [
            'client_id' => Client::factory(),
            'workout_plan_id' => null,
            'workout_day_id' => null,
            'started_at' => $startedAt,
            'completed_at' => null,
            'total_volume_kg' => null,
            'duration_minutes' => null,
            'notes' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(function () {
            return [
                'completed_at' => now(),
                'total_volume_kg' => fake()->randomFloat(2, 1000, 10000),
                'duration_minutes' => fake()->numberBetween(30, 90),
            ];
        });
    }
}
