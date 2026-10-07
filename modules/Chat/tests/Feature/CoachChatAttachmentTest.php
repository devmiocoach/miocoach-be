<?php

namespace Modules\Chat\Tests\Feature;

use App\Enums\Role;
use App\Http\Middleware\EnsureOnboardingComplete;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Chat\Enums\MessageType;
use Modules\Chat\Models\Conversation;
use Tests\TestCase;

class CoachChatAttachmentTest extends TestCase
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

    public function test_coach_can_upload_and_download_attachment_over_session_auth(): void
    {
        Storage::fake('local');
        [$coach, , $conversation] = $this->scenario();

        $upload = $this->withoutMiddleware(EnsureOnboardingComplete::class)
            ->actingAs($coach->user)
            ->postJson("/coach/chat/conversations/{$conversation->id}/attachments", [
                'file' => UploadedFile::fake()->image('scheda.jpg'),
            ]);

        $upload->assertCreated()->assertJsonPath('data.type', MessageType::Image->value);
        $attachmentId = $upload->json('data.attachments.0.id');
        $this->assertNotNull($attachmentId);

        $this->withoutMiddleware(EnsureOnboardingComplete::class)
            ->actingAs($coach->user)
            ->get("/coach/chat/conversations/{$conversation->id}/attachments/{$attachmentId}")
            ->assertOk();
    }

    public function test_non_participant_coach_cannot_download_attachment_over_session_auth(): void
    {
        Storage::fake('local');
        [$coach, , $conversation] = $this->scenario();
        $outsider = Coach::factory()->create();
        $outsider->user->assignRole(Role::COACH);

        $upload = $this->withoutMiddleware(EnsureOnboardingComplete::class)
            ->actingAs($coach->user)
            ->postJson("/coach/chat/conversations/{$conversation->id}/attachments", [
                'file' => UploadedFile::fake()->image('scheda.jpg'),
            ]);
        $attachmentId = $upload->json('data.attachments.0.id');

        $this->withoutMiddleware(EnsureOnboardingComplete::class)
            ->actingAs($outsider->user)
            ->get("/coach/chat/conversations/{$conversation->id}/attachments/{$attachmentId}")
            ->assertForbidden();
    }

    public function test_cross_conversation_attachment_access_is_rejected_over_session_auth(): void
    {
        Storage::fake('local');
        [$coach, , $conversation] = $this->scenario();
        [$otherCoach, , $otherConversation] = $this->scenario();

        $upload = $this->withoutMiddleware(EnsureOnboardingComplete::class)
            ->actingAs($coach->user)
            ->postJson("/coach/chat/conversations/{$conversation->id}/attachments", [
                'file' => UploadedFile::fake()->image('scheda.jpg'),
            ]);
        $attachmentId = $upload->json('data.attachments.0.id');

        // otherCoach IS a participant of otherConversation, but the attachment belongs to $conversation.
        $this->withoutMiddleware(EnsureOnboardingComplete::class)
            ->actingAs($otherCoach->user)
            ->get("/coach/chat/conversations/{$otherConversation->id}/attachments/{$attachmentId}")
            ->assertNotFound();

        $this->assertDatabaseCount('message_attachments', 1);
    }
}
