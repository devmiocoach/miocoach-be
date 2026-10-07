<?php

namespace Modules\Progress\Database\Factories;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Progress\Enums\MetricType;
use Modules\Progress\Models\MeasurementGoal;

class MeasurementGoalFactory extends Factory
{
    protected $model = MeasurementGoal::class;

    public function definition(): array
    {
        $metric = $this->faker->randomElement(MetricType::cases());

        return [
            'client_id' => Client::factory(),
            'coach_id' => Coach::factory(),
            'metric_type' => $metric->value,
            'target_value' => $this->faker->randomFloat(1, 50, 100),
            'target_date' => $this->faker->optional(0.5)->dateTimeBetween('+1 month', '+6 months'),
        ];
    }
}
