<?php

namespace App\Modules\Users\Database\Factories;

use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachAvailability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoachAvailability>
 */
class CoachAvailabilityFactory extends Factory
{
    protected $model = CoachAvailability::class;

    public function definition(): array
    {
        $start = fake()->numberBetween(6, 18);

        return [
            'coach_id' => Coach::factory(),
            'day_of_week' => fake()->numberBetween(0, 6),
            'start_time' => sprintf('%02d:00:00', $start),
            'end_time' => sprintf('%02d:00:00', $start + 2),
            'duration_minutes' => 60,
            'is_recurring' => true,
        ];
    }
}
