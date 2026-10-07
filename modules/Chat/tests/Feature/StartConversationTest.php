<?php

namespace Modules\Chat\Tests\Feature;

use App\Enums\Role;
use App\Http\Middleware\EnsureOnboardingComplete;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Chat\Models\Conversation;
use Tests\TestCase;

class StartConversationTest extends TestCase
{
    use RefreshDatabase;

    private function coachWithClient(): array
    {
        $coach = Coach::factory()->create();
        $coach->user->assignRole(Role::COACH);
        $client = Client::factory()->create();
        $coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);

        return [$coach, $client];
    }

    public function test_coach_can_start_a_conversation_with_own_client(): void
    {
        [$coach, $client] = $this->coachWithClient();

        $this->withoutMiddleware(EnsureOnboardingComplete::class)
            ->actingAs($coach->user)
            ->postJson('/coach/chat/conversations', ['client_id' => $client->id])
            ->assertCreated()
            ->assertJsonPath('data.client.id', $client->id);

        $this->assertDatabaseHas('conversations', [
            'coach_id' => $coach->id,
            'client_id' => $client->id,
        ]);
    }

    public function test_starting_twice_reuses_the_same_conversation(): void
    {
        [$coach, $client] = $this->coachWithClient();

        $first = $this->withoutMiddleware(EnsureOnboardingComplete::class)
            ->actingAs($coach->user)
            ->postJson('/coach/chat/conversations', ['client_id' => $client->id])
            ->json('data.id');

        $second = $this->withoutMiddleware(EnsureOnboardingComplete::class)
            ->actingAs($coach->user)
            ->postJson('/coach/chat/conversations', ['client_id' => $client->id])
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, Conversation::count());
    }

    public function test_coach_cannot_start_a_conversation_with_someone_elses_client(): void
    {
        [$coach] = $this->coachWithClient();
        $foreignClient = Client::factory()->create();

        $this->withoutMiddleware(EnsureOnboardingComplete::class)
            ->actingAs($coach->user)
            ->postJson('/coach/chat/conversations', ['client_id' => $foreignClient->id])
            ->assertStatus(403);

        $this->assertDatabaseMissing('conversations', [
            'coach_id' => $coach->id,
            'client_id' => $foreignClient->id,
        ]);
    }

    public function test_chat_page_exposes_the_coach_clients(): void
    {
        [$coach, $client] = $this->coachWithClient();

        $this->withoutMiddleware(EnsureOnboardingComplete::class)
            ->actingAs($coach->user)
            ->get('/coach/chat')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('clients', 1)
                ->where('clients.0.id', $client->id));
    }
}
