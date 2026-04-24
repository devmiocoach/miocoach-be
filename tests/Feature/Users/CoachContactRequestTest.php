<?php

namespace Tests\Feature\Users;

use App\Modules\Users\Models\CoachContactRequest;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Users\Concerns\WithClientUser;
use Tests\Feature\Users\Concerns\WithCoachUser;
use Tests\TestCase;

class CoachContactRequestTest extends TestCase
{
    use WithClientUser, WithCoachUser;

    public function test_client_can_send_contact_request_to_coach(): void
    {
        Notification::fake();

        [$coachUser, $coach] = $this->createCoachUser([], ['is_published' => true]);
        [$clientUser, $client] = $this->createStandaloneClient();

        $response = $this->actingAsClient($clientUser)
            ->postJson("/api/v1/coaches/{$coach->id}/contact-request", [
                'message' => 'Vorrei iniziare un percorso di allenamento.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('coach_contact_requests', [
            'coach_id'  => $coach->id,
            'client_id' => $client->id,
            'status'    => 'pending',
        ]);

        Notification::assertSentOnDemand(
            \App\Modules\Users\Notifications\CoachContactRequestNotification::class
        );
    }

    public function test_client_cannot_send_duplicate_contact_request(): void
    {
        [$coachUser, $coach] = $this->createCoachUser([], ['is_published' => true]);
        [$clientUser, $client] = $this->createStandaloneClient();

        $this->actingAsClient($clientUser)
            ->postJson("/api/v1/coaches/{$coach->id}/contact-request", ['message' => 'First']);

        $response = $this->actingAsClient($clientUser)
            ->postJson("/api/v1/coaches/{$coach->id}/contact-request", ['message' => 'Second']);

        $response->assertUnprocessable();
    }

    public function test_client_cannot_contact_unpublished_coach(): void
    {
        [$coachUser, $coach] = $this->createCoachUser([], ['is_published' => false]);
        [$clientUser, $client] = $this->createStandaloneClient();

        $response = $this->actingAsClient($clientUser)
            ->postJson("/api/v1/coaches/{$coach->id}/contact-request");

        $response->assertNotFound();
    }
}
