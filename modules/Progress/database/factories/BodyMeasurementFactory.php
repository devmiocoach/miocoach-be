<?php

namespace Modules\Progress\Database\Factories;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Progress\Enums\MetricType;
use Modules\Progress\Models\BodyMeasurement;

class BodyMeasurementFactory extends Factory
{
    protected $model = BodyMeasurement::class;

    public function definition(): array
    {
        $metric = $this->faker->randomElement(MetricType::cases());

        return [
            'client_id' => Client::factory(),
            'coach_id' => Coach::factory(),
            'metric_type' => $metric->value,
            'value' => $this->valueForMetric($metric),
            'measured_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'notes' => $this->faker->optional(0.3)->sentence(),
        ];
    }

    public function ofType(MetricType $type): static
    {
        return $this->state([
            'metric_type' => $type->value,
            'value' => $this->valueForMetric($type),
        ]);
    }

    private function valueForMetric(MetricType $metric): float
    {
        return match ($metric) {
            MetricType::Weight => $this->faker->randomFloat(1, 50, 120),
            MetricType::BodyFat => $this->faker->randomFloat(1, 8, 35),
            MetricType::Waist => $this->faker->randomFloat(1, 60, 110),
            MetricType::Chest => $this->faker->randomFloat(1, 80, 130),
            MetricType::Hips => $this->faker->randomFloat(1, 80, 120),
            MetricType::Arms => $this->faker->randomFloat(1, 25, 45),
            MetricType::Thighs => $this->faker->randomFloat(1, 45, 75),
            MetricType::Shoulders => $this->faker->randomFloat(1, 90, 130),
            MetricType::Neck => $this->faker->randomFloat(1, 30, 45),
            MetricType::Calves => $this->faker->randomFloat(1, 30, 45),
        };
    }
}
