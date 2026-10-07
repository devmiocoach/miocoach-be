<?php

namespace Modules\Checkins\Database\Factories;

use App\Modules\Users\Models\Coach;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Checkins\Enums\CheckinFrequency;
use Modules\Checkins\Models\CheckinForm;

/**
 * @extends Factory<CheckinForm>
 */
class CheckinFormFactory extends Factory
{
    protected $model = CheckinForm::class;

    public function definition(): array
    {
        return [
            'coach_id' => Coach::factory(),
            'title' => $this->faker->sentence(3),
            'frequency' => CheckinFrequency::Weekly,
            'send_time' => '19:00',
            'questions' => [
                ['id' => 'q1', 'text' => 'Come ti senti?', 'type' => 'rating', 'required' => true, 'order' => 1],
                ['id' => 'q2', 'text' => 'Note libere', 'type' => 'text', 'required' => false, 'order' => 2],
            ],
            'is_active' => true,
        ];
    }
}
