<?php

namespace Modules\CoachCommerce\Database\Factories;

use App\Modules\Users\Models\Coach;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CoachCommerce\Models\SubscriptionPlan;

class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return [
            'coach_id' => Coach::factory(),
            'name' => $this->faker->words(2, true).' Plan',
            'description' => $this->faker->sentence(),
            'price_monthly' => $this->faker->randomFloat(2, 50, 300),
            'price_yearly' => $this->faker->randomFloat(2, 500, 2500),
            'sessions_per_month' => $this->faker->numberBetween(4, 20),
            'features' => ['Accesso illimitato', 'Supporto prioritario'],
            'is_active' => true,
            'stripe_price_id' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
