<?php

namespace Modules\Checkins\Database\Factories;

use App\Modules\Users\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Checkins\Models\CheckinForm;
use Modules\Checkins\Models\CheckinResponse;

/**
 * @extends Factory<CheckinResponse>
 */
class CheckinResponseFactory extends Factory
{
    protected $model = CheckinResponse::class;

    public function definition(): array
    {
        return [
            'checkin_form_id' => CheckinForm::factory(),
            'client_id' => Client::factory(),
            'answers' => [
                ['questionId' => 'q1', 'value' => 4],
                ['questionId' => 'q2', 'value' => 'Tutto ok'],
            ],
            'submitted_at' => now(),
        ];
    }
}
