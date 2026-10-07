<?php

namespace Modules\Progress\Database\Factories;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Progress\Enums\PhotoPose;
use Modules\Progress\Models\ProgressPhoto;

class ProgressPhotoFactory extends Factory
{
    protected $model = ProgressPhoto::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'coach_id' => Coach::factory(),
            'storage_key' => 'progress-photos/'.$this->faker->uuid().'.jpg',
            'thumbnail_key' => null,
            'pose' => $this->faker->randomElement(PhotoPose::cases())->value,
            'taken_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'notes' => $this->faker->optional(0.3)->sentence(),
        ];
    }
}
