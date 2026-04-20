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
}
