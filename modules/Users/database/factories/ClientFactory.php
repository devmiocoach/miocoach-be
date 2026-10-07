<?php

namespace App\Modules\Users\Database\Factories;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\Users\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['type' => UserType::ATLETA->value]),
            'goals' => [fake()->sentence()],
            'birth_date' => fake()->dateTimeBetween('-50 years', '-16 years'),
            'gender' => fake()->randomElement(['male', 'female']),
            'height_cm' => fake()->randomFloat(1, 150, 200),
            'weight_kg' => fake()->randomFloat(2, 45, 120),
            'status' => 'active',
            'joined_at' => now(),
            'timezone' => 'Europe/Rome',
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
