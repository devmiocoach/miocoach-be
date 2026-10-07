<?php

namespace Modules\Chat\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Chat\Enums\MessageType;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\Message;

/** @extends Factory<Message> */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'sender_user_id' => User::factory(),
            'type' => MessageType::Text,
            'body' => $this->faker->sentence(),
            'meta' => null,
        ];
    }
}
