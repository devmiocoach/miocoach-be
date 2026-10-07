<?php

namespace Modules\Chat\Tests\Feature;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Modules\Chat\Data\SendMessageData;
use Modules\Chat\Enums\MessageType;
use Modules\Chat\Events\MessageRead;
use Modules\Chat\Events\MessageSent;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Services\MessageService;
use Tests\TestCase;

class MessageServiceTest extends TestCase
{
    use RefreshDatabase;

    private function conversation(): Conversation
    {
        return Conversation::factory()->create([
            'coach_id' => Coach::factory()->create()->id,
            'client_id' => Client::factory()->create()->id,
        ]);
    }

    public function test_send_persists_updates_timestamp_and_broadcasts(): void
    {
        Event::fake([MessageSent::class]);
        $conversation = $this->conversation();
        $sender = $conversation->coach->user;
        $service = app(MessageService::class);

        $message = $service->send(
            $conversation,
            $sender,
            new SendMessageData(MessageType::Text, 'ciao'),
        );

        $this->assertDatabaseHas('messages', ['id' => $message->id, 'body' => 'ciao']);
        $this->assertNotNull($conversation->fresh()->last_message_at);
        Event::assertDispatched(MessageSent::class, fn ($e) => $e->message->id === $message->id);
    }

    public function test_mark_read_marks_others_messages_and_broadcasts(): void
    {
        Event::fake([MessageRead::class]);
        $conversation = $this->conversation();
        $coachUser = $conversation->coach->user;
        $clientUser = $conversation->client->user;
        $service = app(MessageService::class);

        $m = $service->send($conversation, $coachUser, new SendMessageData(MessageType::Text, 'hi'));

        $ids = $service->markRead($conversation, $clientUser);

        $this->assertEquals([$m->id], $ids);
        $this->assertDatabaseHas('message_reads', ['message_id' => $m->id, 'user_id' => $clientUser->id]);
        $this->assertEquals(0, $service->unreadCountFor($conversation, $clientUser));
        Event::assertDispatched(MessageRead::class);
    }

    public function test_send_attachment_deletes_the_orphaned_file_when_the_transaction_fails(): void
    {
        Storage::fake('local');
        $conversation = $this->conversation();
        $sender = $conversation->coach->user;

        // The file is stored before the DB transaction; force the transaction to blow up.
        DB::shouldReceive('transaction')->once()->andThrow(new \RuntimeException('boom'));

        try {
            app(MessageService::class)->sendAttachment(
                $conversation,
                $sender,
                UploadedFile::fake()->image('scheda.jpg'),
            );
            $this->fail('expected the transaction failure to propagate');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_mark_read_ignores_own_messages(): void
    {
        Event::fake([MessageRead::class]);
        $conversation = $this->conversation();
        $coachUser = $conversation->coach->user;
        $service = app(MessageService::class);

        $service->send($conversation, $coachUser, new SendMessageData(MessageType::Text, 'mine'));
        $ids = $service->markRead($conversation, $coachUser);

        $this->assertEmpty($ids);
        Event::assertNotDispatched(MessageRead::class);
    }
}
