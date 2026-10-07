<?php

namespace Modules\Chat\Tests\Feature\Api;

use App\Enums\Role;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Http\Middleware\JwtAuthenticate;
use Modules\Auth\Http\Middleware\RequireVerified;
use Modules\Chat\Models\Conversation;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingCoach(): array
    {
        $coach = Coach::factory()->create();
        $coach->user->assignRole(Role::COACH);
        $client = Client::factory()->create();
        $coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);
        $conversation = Conversation::factory()->create([
            'coach_id' => $coach->id,
            'client_id' => $client->id,
        ]);

        return [$coach, $client, $conversation];
    }

    public function test_coach_can_send_and_list_messages(): void
    {
        [$coach, , $conversation] = $this->actingCoach();

        $send = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coach->user)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", ['body' => 'ciao']);

        $send->assertCreated()->assertJsonPath('data.body', 'ciao');

        $list = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coach->user)
            ->getJson("/api/v1/conversations/{$conversation->id}/messages");

        $list->assertOk()->assertJsonPath('data.0.body', 'ciao');
    }

    public function test_non_participant_cannot_read(): void
    {
        [, , $conversation] = $this->actingCoach();
        $outsider = Coach::factory()->create();
        $outsider->user->assignRole(Role::COACH);

        $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($outsider->user)
            ->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->assertForbidden();
    }

    public function test_client_marks_coach_messages_read(): void
    {
        [$coach, $client, $conversation] = $this->actingCoach();
        $client->user->assignRole(Role::CLIENT);

        $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coach->user)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", ['body' => 'hi']);

        $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($client->user)
            ->postJson("/api/v1/conversations/{$conversation->id}/read")
            ->assertOk()
            ->assertJsonCount(1, 'message_ids');
    }
}
