<?php

namespace Modules\Chat\Tests\Feature\Api;

use App\Enums\Role;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Auth\Http\Middleware\JwtAuthenticate;
use Modules\Auth\Http\Middleware\RequireVerified;
use Modules\Chat\Enums\MessageType;
use Modules\Chat\Models\Conversation;
use Tests\TestCase;

class ChatAttachmentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Coach, 1: Client, 2: Conversation}
     */
    private function scenario(): array
    {
        $coach = Coach::factory()->create();
        $coach->user->assignRole(Role::COACH);
        $client = Client::factory()->create();
        $coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);
        $conversation = Conversation::factory()->create(['coach_id' => $coach->id, 'client_id' => $client->id]);

        return [$coach, $client, $conversation];
    }

    public function test_coach_uploads_image_attachment(): void
    {
        Storage::fake('local');
        [$coach, , $conversation] = $this->scenario();

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coach->user)
            ->postJson("/api/v1/conversations/{$conversation->id}/attachments", [
                'file' => UploadedFile::fake()->image('scheda.jpg'),
            ]);

        $response->assertCreated()->assertJsonPath('data.type', MessageType::Image->value);
        $response->assertJsonPath('data.attachments.0.original_name', 'scheda.jpg');
        $this->assertNotNull($response->json('data.attachments.0.download_url'));
        $this->assertDatabaseCount('message_attachments', 1);
    }

    public function test_coach_uploads_file_attachment(): void
    {
        Storage::fake('local');
        [$coach, , $conversation] = $this->scenario();

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coach->user)
            ->postJson("/api/v1/conversations/{$conversation->id}/attachments", [
                'file' => UploadedFile::fake()->create('scheda.pdf', 500, 'application/pdf'),
            ]);

        $response->assertCreated()->assertJsonPath('data.type', MessageType::File->value);
        $response->assertJsonPath('data.attachments.0.original_name', 'scheda.pdf');
        $this->assertDatabaseCount('message_attachments', 1);
    }

    public function test_rejects_disallowed_mime_type(): void
    {
        Storage::fake('local');
        [$coach, , $conversation] = $this->scenario();

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coach->user)
            ->postJson("/api/v1/conversations/{$conversation->id}/attachments", [
                'file' => UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload'),
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['file'], 'error.fields');
        $this->assertDatabaseCount('message_attachments', 0);
    }

    public function test_rejects_oversized_file(): void
    {
        Storage::fake('local');
        [$coach, , $conversation] = $this->scenario();

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coach->user)
            ->postJson("/api/v1/conversations/{$conversation->id}/attachments", [
                'file' => UploadedFile::fake()->create('scheda.pdf', 11000, 'application/pdf'),
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['file'], 'error.fields');
        $this->assertDatabaseCount('message_attachments', 0);
    }

    public function test_participant_can_download_attachment(): void
    {
        Storage::fake('local');
        [$coach, , $conversation] = $this->scenario();

        $upload = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coach->user)
            ->postJson("/api/v1/conversations/{$conversation->id}/attachments", [
                'file' => UploadedFile::fake()->image('scheda.jpg'),
            ]);
        $attachmentId = $upload->json('data.attachments.0.id');

        $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coach->user)
            ->get("/api/v1/conversations/{$conversation->id}/attachments/{$attachmentId}")
            ->assertOk();
    }

    public function test_non_participant_cannot_download_attachment(): void
    {
        Storage::fake('local');
        [$coach, , $conversation] = $this->scenario();
        $outsider = Coach::factory()->create();
        $outsider->user->assignRole(Role::COACH);

        $upload = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coach->user)
            ->postJson("/api/v1/conversations/{$conversation->id}/attachments", [
                'file' => UploadedFile::fake()->image('scheda.jpg'),
            ]);
        $attachmentId = $upload->json('data.attachments.0.id');

        $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($outsider->user)
            ->get("/api/v1/conversations/{$conversation->id}/attachments/{$attachmentId}")
            ->assertForbidden();
    }

    public function test_cross_conversation_attachment_access_is_rejected(): void
    {
        Storage::fake('local');
        [$coach, , $conversation] = $this->scenario();
        [$otherCoach, , $otherConversation] = $this->scenario();

        $upload = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coach->user)
            ->postJson("/api/v1/conversations/{$conversation->id}/attachments", [
                'file' => UploadedFile::fake()->image('scheda.jpg'),
            ]);
        $attachmentId = $upload->json('data.attachments.0.id');

        // otherCoach IS a participant of otherConversation, but the attachment belongs to $conversation.
        $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($otherCoach->user)
            ->get("/api/v1/conversations/{$otherConversation->id}/attachments/{$attachmentId}")
            ->assertNotFound();

        $this->assertDatabaseCount('message_attachments', 1);
    }
}
