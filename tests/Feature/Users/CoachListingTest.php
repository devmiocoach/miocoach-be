<?php

namespace Tests\Feature\Users;

use App\Models\User;
use App\Modules\Users\Models\Client;
use Illuminate\Auth\Events\Registered;
use Spatie\Permission\Models\Role;
use Tests\Feature\Users\Concerns\WithClientUser;
use Tests\Feature\Users\Concerns\WithCoachUser;
use Tests\TestCase;

class CoachListingTest extends TestCase
{
    use WithClientUser, WithCoachUser;

    public function test_client_profile_created_on_registration(): void
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $user->assignRole($role);

        $this->assertNull($user->fresh()->client);

        event(new Registered($user));

        $this->assertNotNull($user->fresh()->client);
        $this->assertNull($user->fresh()->client->coach_id);
    }

    public function test_registration_listener_does_not_duplicate_client(): void
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $user->assignRole($role);

        $existing            = new Client();
        $existing->user_id   = $user->id;
        $existing->joined_at = now();
        $existing->save();

        event(new Registered($user));

        $this->assertCount(1, Client::where('user_id', $user->id)->get());
    }

    public function test_public_can_list_published_coaches(): void
    {
        [$u1, $c1] = $this->createCoachUser(['name' => 'Marta Coach'], ['is_published' => true, 'city' => 'Milano']);
        [$u2, $c2] = $this->createCoachUser(['name' => 'Luca Coach'], ['is_published' => false]);

        $response = $this->getJson('/api/v1/coaches');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($c1->id, $ids->toArray());
        $this->assertNotContains($c2->id, $ids->toArray());
    }

    public function test_coach_listing_filters_by_city(): void
    {
        [$u1, $milan]  = $this->createCoachUser([], ['is_published' => true, 'city' => 'Milano']);
        [$u2, $rome]   = $this->createCoachUser([], ['is_published' => true, 'city' => 'Roma']);

        $response = $this->getJson('/api/v1/coaches?city=Milano');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($milan->id, $ids->toArray());
        $this->assertNotContains($rome->id, $ids->toArray());
    }

    public function test_coach_listing_filters_by_mode(): void
    {
        [$u1, $online]  = $this->createCoachUser([], ['is_published' => true, 'mode' => 'online']);
        [$u2, $person]  = $this->createCoachUser([], ['is_published' => true, 'mode' => 'in_person']);

        $response = $this->getJson('/api/v1/coaches?mode=online');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($online->id, $ids->toArray());
        $this->assertNotContains($person->id, $ids->toArray());
    }

    public function test_coach_listing_filters_by_max_price(): void
    {
        [$u1, $cheap]     = $this->createCoachUser([], ['is_published' => true, 'price_per_session' => 30]);
        [$u2, $expensive] = $this->createCoachUser([], ['is_published' => true, 'price_per_session' => 100]);

        $response = $this->getJson('/api/v1/coaches?price_max=50');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($cheap->id, $ids->toArray());
        $this->assertNotContains($expensive->id, $ids->toArray());
    }

    public function test_coach_listing_search_by_name(): void
    {
        [$u1, $c1] = $this->createCoachUser(['name' => 'Federica Neri'], ['is_published' => true]);
        [$u2, $c2] = $this->createCoachUser(['name' => 'Paolo Bianchi'], ['is_published' => true]);

        $response = $this->getJson('/api/v1/coaches?search=Federica');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($c1->id, $ids->toArray());
        $this->assertNotContains($c2->id, $ids->toArray());
    }
}
