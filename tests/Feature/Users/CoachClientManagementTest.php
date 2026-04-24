<?php

namespace Tests\Feature\Users;

use App\Modules\Users\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Users\Concerns\WithCoachUser;
use Tests\TestCase;

class CoachClientManagementTest extends TestCase
{
    use WithCoachUser;

    public function test_client_model_has_new_fields(): void
    {
        [$user, $coach] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach);

        $client->tags  = ['premium', 'online'];
        $client->phone = '+39 333 1234567';
        $client->save();

        $fresh = $client->fresh();
        $this->assertEquals(['premium', 'online'], $fresh->tags);
        $this->assertEquals('+39 333 1234567', $fresh->phone);
    }

    public function test_coach_can_list_clients_with_default_ordering(): void
    {
        [$coachUser, $coach] = $this->createCoachUser();

        [$u1, $c1] = $this->createClientForCoach($coach, [], [
            'subscription_expires_at' => now()->addDays(30),
        ]);
        [$u2, $c2] = $this->createClientForCoach($coach, [], [
            'subscription_expires_at' => now()->addDays(5),
        ]);

        $response = $this->actingAsCoach($coachUser)
            ->getJson('/api/v1/coaches/me/clients');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals($c2->id, $data[0]['id']);
        $this->assertEquals($c1->id, $data[1]['id']);
    }

    public function test_coach_can_filter_clients_by_status(): void
    {
        [$coachUser, $coach] = $this->createCoachUser();
        [$u1, $active]   = $this->createClientForCoach($coach);
        [$u2, $inactive] = $this->createClientForCoach($coach);

        $inactive->status = 'inactive';
        $inactive->save();

        $response = $this->actingAsCoach($coachUser)
            ->getJson('/api/v1/coaches/me/clients?status=inactive');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($inactive->id, $data[0]['id']);
    }

    public function test_coach_can_search_clients_by_name(): void
    {
        [$coachUser, $coach] = $this->createCoachUser();
        [$u1, $c1] = $this->createClientForCoach($coach, ['name' => 'Alice Rossi']);
        [$u2, $c2] = $this->createClientForCoach($coach, ['name' => 'Bob Bianchi']);

        $response = $this->actingAsCoach($coachUser)
            ->getJson('/api/v1/coaches/me/clients?search=Alice');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($c1->id, $data[0]['id']);
    }

    public function test_client_resource_exposes_flat_user_fields(): void
    {
        [$coachUser, $coach] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach, ['name' => 'Mario Rossi']);
        $client->phone = '+39 333 0000000';
        $client->tags  = ['vip'];
        $client->save();

        $response = $this->actingAsCoach($coachUser)
            ->getJson('/api/v1/coaches/me/clients');

        $response->assertOk()
            ->assertJsonPath('data.0.first_name', 'Mario')
            ->assertJsonPath('data.0.last_name', 'Rossi')
            ->assertJsonPath('data.0.phone', '+39 333 0000000')
            ->assertJsonPath('data.0.tags.0', 'vip');
    }

    public function test_coach_can_create_client_for_existing_user(): void
    {
        [$coachUser, $coach] = $this->createCoachUser();

        $existingUser = \App\Models\User::factory()->create([
            'name'              => 'Lucia Verdi',
            'email'             => 'lucia@test.com',
            'email_verified_at' => now(),
        ]);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $existingUser->assignRole('client');
        $existingClient = new \App\Modules\Users\Models\Client();
        $existingClient->user_id   = $existingUser->id;
        $existingClient->joined_at = now();
        $existingClient->save();

        $response = $this->actingAsCoach($coachUser)
            ->postJson('/api/v1/coaches/me/clients', [
                'email'      => 'lucia@test.com',
                'first_name' => 'Lucia',
                'last_name'  => 'Verdi',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('clients', [
            'user_id'  => $existingUser->id,
            'coach_id' => $coach->id,
        ]);
    }

    public function test_coach_create_client_with_new_email_sends_invitation(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        [$coachUser, $coach] = $this->createCoachUser();

        $response = $this->actingAsCoach($coachUser)
            ->postJson('/api/v1/coaches/me/clients', [
                'email'      => 'newclient@test.com',
                'first_name' => 'Nuovo',
                'last_name'  => 'Cliente',
            ]);

        $response->assertStatus(202);
        $this->assertDatabaseHas('coach_invitations', [
            'coach_id' => $coach->id,
            'email'    => 'newclient@test.com',
            'status'   => 'pending',
        ]);
    }

    public function test_coach_can_get_full_client_card(): void
    {
        [$coachUser, $coach] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach, ['name' => 'Sara Neri'], [
            'phone' => '+39 340 1234567',
            'tags'  => ['premium'],
        ]);

        $response = $this->actingAsCoach($coachUser)
            ->getJson("/api/v1/coaches/me/clients/{$client->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonPath('data.first_name', 'Sara')
            ->assertJsonPath('data.phone', '+39 340 1234567')
            ->assertJsonPath('data.tags.0', 'premium')
            ->assertJsonPath('data.anamnesis', null)
            ->assertJsonPath('data.notes', [])
            ->assertJsonPath('data.files', []);
    }

    public function test_coach_cannot_get_another_coachs_client(): void
    {
        [$coachUser, $coach]   = $this->createCoachUser();
        [$coach2User, $coach2] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach2);

        $response = $this->actingAsCoach($coachUser)
            ->getJson("/api/v1/coaches/me/clients/{$client->id}");

        $response->assertForbidden();
    }

    public function test_coach_can_update_client(): void
    {
        [$coachUser, $coach] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach);

        $response = $this->actingAsCoach($coachUser)
            ->putJson("/api/v1/coaches/me/clients/{$client->id}", [
                'phone'     => '+39 333 9999999',
                'tags'      => ['vip', 'online'],
                'weight_kg' => 75.5,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.phone', '+39 333 9999999')
            ->assertJsonPath('data.tags.0', 'vip')
            ->assertJsonPath('data.weight_kg', '75.50');
    }

    public function test_coach_can_soft_delete_client(): void
    {
        [$coachUser, $coach] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach);

        $response = $this->actingAsCoach($coachUser)
            ->deleteJson("/api/v1/coaches/me/clients/{$client->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }
}
