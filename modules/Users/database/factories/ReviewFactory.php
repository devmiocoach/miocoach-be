<?php

namespace App\Modules\Users\Database\Factories;

use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'coach_id' => Coach::factory(),
            'client_id' => null,
            'booking_id' => null,
            'rating' => fake()->numberBetween(1, 5),
            'title' => fake()->optional()->sentence(3),
            'body' => fake()->optional()->paragraph(),
            'is_verified' => false,
            'published_at' => now(),
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['published_at' => null]);
    }

    public function verified(): static
    {
        return $this->state(['is_verified' => true]);
    }
}
