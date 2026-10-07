<?php

namespace App\Modules\Users\Database\Factories;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\Users\Models\Coach;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coach>
 */
class CoachFactory extends Factory
{
    protected $model = Coach::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['type' => UserType::COACH->value]),
            'slug' => fake()->unique()->slug(2),
            'bio' => fake()->paragraph(),
            'tagline' => fake()->sentence(),
            'specializations' => [fake()->word(), fake()->word()],
            'languages' => ['it'],
            'mode' => fake()->randomElement(['online', 'in_person', 'hybrid']),
            'gender' => fake()->optional()->randomElement(['male', 'female']),
            'city' => fake()->city(),
            'province' => fake()->stateAbbr(),
            'hourly_rate' => fake()->randomFloat(2, 30, 150),
            'max_clients' => fake()->numberBetween(5, 50),
            'is_verified' => false,
            'is_visible' => true,
            'is_published' => true,
            'accepts_direct_booking' => true,
        ];
    }

    public function verified(): static
    {
        return $this->state(['is_verified' => true]);
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }
}
