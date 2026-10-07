<?php

namespace Modules\Chat\Database\Factories;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Chat\Models\Conversation;

/** @extends Factory<Conversation> */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'coach_id' => Coach::factory(),
            'client_id' => Client::factory(),
            'last_message_at' => null,
        ];
    }
}
