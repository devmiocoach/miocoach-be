<?php

namespace Modules\Chat\Tests\Feature;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Chat\Events\MessageRead;
use Modules\Chat\Events\MessageSent;
use Modules\Chat\Models\Message;
use Tests\TestCase;

class BroadcastEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_sent_broadcasts_on_conversation_channel(): void
    {
        $message = Message::factory()->create();
        $event = new MessageSent($message);

        $channels = $event->broadcastOn();
        $this->assertInstanceOf(PrivateChannel::class, $channels);
        $this->assertEquals(
            'private-chat.conversation.'.$message->conversation_id,
            $channels->name
        );
        $this->assertEquals('message.sent', $event->broadcastAs());
        $this->assertEquals($message->id, $event->broadcastWith()['id']);
    }

    public function test_message_read_payload(): void
    {
        $event = new MessageRead(5, 9, [1, 2, 3]);

        $this->assertEquals('message.read', $event->broadcastAs());
        $this->assertEquals(
            ['conversation_id' => 5, 'reader_user_id' => 9, 'message_ids' => [1, 2, 3]],
            $event->broadcastWith()
        );
    }
}
