<?php

namespace Tests\Feature\Users;

use Tests\Feature\Users\Concerns\WithCoachUser;
use Tests\TestCase;

class CoachClientNoteTest extends TestCase
{
    use WithCoachUser;

    public function test_coach_can_add_note_to_client(): void
    {
        [$coachUser, $coach] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach);

        $response = $this->actingAsCoach($coachUser)
            ->postJson("/api/v1/coaches/me/clients/{$client->id}/notes", [
                'content' => 'Il cliente ha problemi alla spalla sinistra.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.content', 'Il cliente ha problemi alla spalla sinistra.');

        $this->assertDatabaseHas('client_notes', [
            'client_id' => $client->id,
            'coach_id'  => $coach->id,
        ]);
    }

    public function test_note_is_immutable_after_creation(): void
    {
        [$coachUser, $coach] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach);

        $this->actingAsCoach($coachUser)
            ->postJson("/api/v1/coaches/me/clients/{$client->id}/notes", [
                'content' => 'Original note',
            ]);

        $note = \App\Modules\Users\Models\ClientNote::first();
        $this->assertNotNull($note->created_at);
        $this->assertArrayNotHasKey('updated_at', $note->toArray());
    }
}
