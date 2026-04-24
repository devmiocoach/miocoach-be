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

    public function test_coach_can_list_contact_requests(): void
    {
        [$coachUser, $coach] = $this->createCoachUser([], ['is_published' => true]);
        [$clientUser, $client] = $this->createStandaloneClient();

        $req = new CoachContactRequest();
        $req->coach_id  = $coach->id;
        $req->client_id = $client->id;
        $req->message   = 'Voglio allenarti';
        $req->status    = 'pending';
        $req->save();

        $response = $this->actingAsCoach($coachUser)
            ->getJson('/api/v1/coaches/me/contact-requests');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_coach_can_accept_contact_request(): void
    {
        [$coachUser, $coach] = $this->createCoachUser([], ['is_published' => true]);
        [$clientUser, $client] = $this->createStandaloneClient();

        $req = new CoachContactRequest();
        $req->coach_id  = $coach->id;
        $req->client_id = $client->id;
        $req->status    = 'pending';
        $req->save();

        $response = $this->actingAsCoach($coachUser)
            ->putJson("/api/v1/coaches/me/contact-requests/{$req->id}", [
                'action' => 'accept',
            ]);

        $response->assertOk()->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('clients', [
            'id'       => $client->id,
            'coach_id' => $coach->id,
        ]);
    }

    public function test_coach_can_decline_contact_request(): void
    {
        [$coachUser, $coach] = $this->createCoachUser([], ['is_published' => true]);
        [$clientUser, $client] = $this->createStandaloneClient();

        $req = new CoachContactRequest();
        $req->coach_id  = $coach->id;
        $req->client_id = $client->id;
        $req->status    = 'pending';
        $req->save();

        $response = $this->actingAsCoach($coachUser)
            ->putJson("/api/v1/coaches/me/contact-requests/{$req->id}", [
                'action' => 'decline',
            ]);

        $response->assertOk()->assertJsonPath('data.status', 'declined');

        $this->assertDatabaseMissing('clients', [
            'id'       => $client->id,
            'coach_id' => $coach->id,
        ]);
    }
}
