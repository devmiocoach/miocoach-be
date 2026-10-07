<?php

namespace Modules\Chat\Tests\Feature;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Chat\Enums\MessageType;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\Message;
use Tests\TestCase;

class ConversationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_relates_coach_client_and_messages(): void
    {
        $coach = Coach::factory()->create();
        $client = Client::factory()->create();
        $conversation = Conversation::factory()->create([
            'coach_id' => $coach->id,
            'client_id' => $client->id,
        ]);

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_user_id' => $coach->user->id,
            'type' => MessageType::Text,
            'body' => 'ciao',
        ]);

        $this->assertEquals($coach->id, $conversation->coach->id);
        $this->assertEquals($client->id, $conversation->client->id);
        $this->assertTrue($conversation->messages->contains($message));
        $this->assertInstanceOf(MessageType::class, $message->type);
        $this->assertEquals('ciao', $conversation->latestMessage->body);
    }
}
