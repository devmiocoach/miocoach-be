<?php

namespace App\Modules\Users\Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\ClientNote;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Providers\UsersServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Http\Middleware\JwtAuthenticate;
use Modules\Auth\Http\Middleware\RequireVerified;
use Tests\TestCase;

class ClientNoteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->app->register(UsersServiceProvider::class);
    }

    private function makeCoachUser(): array
    {
        $coach = Coach::factory()->create();
        $coachUser = $coach->user;
        $coachUser->assignRole(Role::COACH);
        $coachUser->setRelation('coach', $coach);

        return [$coachUser, $coach];
    }

    private function makeClientAttachedTo(Coach $coach): Client
    {
        $client = Client::factory()->create();
        $coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);

        return $client;
    }

    private function makeNote(Client $client, Coach $coach, string $content = 'Test note'): ClientNote
    {
        $note = new ClientNote;
        $note->client_id = $client->id;
        $note->coach_id = $coach->id;
        $note->content = $content;
        $note->created_at = now();
        $note->save();

        return $note;
    }

    public function test_coach_can_list_client_notes(): void
    {
        [$coachUser, $coach] = $this->makeCoachUser();
        $client = $this->makeClientAttachedTo($coach);
        $this->makeNote($client, $coach, 'First note');
        $this->makeNote($client, $coach, 'Second note');

        // Note from another coach should not appear
        $otherCoach = Coach::factory()->create();
        $otherCoach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);
        $this->makeNote($client, $otherCoach, 'Other coach note');

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coachUser)
            ->getJson("/api/v1/coaches/me/clients/{$client->id}/notes");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'content', 'created_at']]])
            ->assertJsonCount(2, 'data');
    }

    public function test_coach_cannot_list_notes_for_unowned_client(): void
    {
        [$coachUser, $coach] = $this->makeCoachUser();
        $client = Client::factory()->create(); // Not attached to this coach

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coachUser)
            ->getJson("/api/v1/coaches/me/clients/{$client->id}/notes");

        $response->assertStatus(403);
    }

    public function test_coach_can_delete_own_note(): void
    {
        [$coachUser, $coach] = $this->makeCoachUser();
        $client = $this->makeClientAttachedTo($coach);
        $note = $this->makeNote($client, $coach);

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coachUser)
            ->deleteJson("/api/v1/coaches/me/clients/{$client->id}/notes/{$note->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('client_notes', ['id' => $note->id]);
    }

    public function test_coach_cannot_delete_another_coachs_note(): void
    {
        [$coachUser, $coach] = $this->makeCoachUser();
        $client = $this->makeClientAttachedTo($coach);

        $otherCoach = Coach::factory()->create();
        $otherCoach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);
        $otherNote = $this->makeNote($client, $otherCoach, 'Other coach note');

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coachUser)
            ->deleteJson("/api/v1/coaches/me/clients/{$client->id}/notes/{$otherNote->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('client_notes', ['id' => $otherNote->id]);
    }
}
