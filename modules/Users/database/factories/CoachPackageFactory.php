<?php

namespace App\Modules\Users\Database\Factories;

use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoachPackage>
 */
class CoachPackageFactory extends Factory
{
    protected $model = CoachPackage::class;

    public function definition(): array
    {
        return [
            'coach_id' => Coach::factory(),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->optional()->sentence(),
            'sessions_count' => $this->faker->numberBetween(1, 20),
            'price_cents' => $this->faker->numberBetween(5000, 50000),
            'validity_days' => $this->faker->randomElement([30, 60, 90, 180, 365]),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
