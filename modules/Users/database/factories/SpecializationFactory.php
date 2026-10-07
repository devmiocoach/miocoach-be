<?php

namespace App\Modules\Users\Database\Factories;

use App\Modules\Users\Models\Specialization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Specialization>
 */
class SpecializationFactory extends Factory
{
    protected $model = Specialization::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'is_custom' => false,
            'created_by' => null,
        ];
    }
}
