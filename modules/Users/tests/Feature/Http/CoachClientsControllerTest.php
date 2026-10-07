<?php

namespace App\Modules\Users\Tests\Feature\Http;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Providers\UsersServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Providers\AuthServiceProvider;
use Modules\Bookings\Providers\BookingsServiceProvider;
use Tests\TestCase;

class CoachClientsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->app->register(UsersServiceProvider::class);
        $this->app->register(AuthServiceProvider::class);
        $this->app->register(BookingsServiceProvider::class);
    }

    private function createCoachUser(): User
    {
        $user = User::factory()->create(['type' => UserType::COACH]);
        $user->coach()->save(Coach::factory()->make(['onboarding_completed_at' => now()]));
        $user->assignRole('coach');

        return $user;
    }

    private function createClientWithUser(array $clientData = []): Client
    {
        $user = User::factory()->create(['type' => UserType::ATLETA]);

        return Client::factory()->create(array_merge(['user_id' => $user->id], $clientData));
    }

    public function test_coach_can_view_clients_list(): void
    {
        $user = $this->createCoachUser();
        $coach = $user->coach;

        $client = $this->createClientWithUser();
        $coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);

        $response = $this->actingAs($user)->get(route('coach.clients.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Users::Coach/Clients/Index', false)
            ->has('clients', 1)
            ->has('availableTags')
        );
    }

    public function test_all_clients_are_returned(): void
    {
        $user = $this->createCoachUser();
        $coach = $user->coach;

        for ($i = 0; $i < 20; $i++) {
            $client = $this->createClientWithUser();
            $coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);
        }

        $response = $this->actingAs($user)->get(route('coach.clients.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('clients', 20)
        );
    }

    public function test_client_data_structure_is_correct(): void
    {
        $user = $this->createCoachUser();
        $coach = $user->coach;

        $clientUser = User::factory()->create(['name' => 'Test Client', 'email' => 'test@example.com', 'type' => UserType::ATLETA]);
        $client = Client::factory()->create([
            'user_id' => $clientUser->id,
            'tags' => ['vip', 'morning'],
            'subscription_expires_at' => now()->addDays(30),
        ]);
        $coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);

        $response = $this->actingAs($user)->get(route('coach.clients.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('clients', 1)
            ->where('clients.0.id', $client->id)
            ->where('clients.0.name', 'Test Client')
            ->where('clients.0.email', 'test@example.com')
            ->where('clients.0.status', 'active')
            ->where('clients.0.tags', ['vip', 'morning'])
            ->has('clients.0.subscription_expires_at')
        );
    }

    public function test_only_own_clients_are_returned(): void
    {
        $user = $this->createCoachUser();
        $coach = $user->coach;

        $otherCoachUser = $this->createCoachUser();
        $otherCoach = $otherCoachUser->coach;

        $ownClient = $this->createClientWithUser();
        $otherClient = $this->createClientWithUser();

        $coach->athletes()->attach($ownClient->id, ['status' => 'active', 'started_at' => now()]);
        $otherCoach->athletes()->attach($otherClient->id, ['status' => 'active', 'started_at' => now()]);

        $response = $this->actingAs($user)->get(route('coach.clients.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('clients', 1)
            ->where('clients.0.id', $ownClient->id)
        );
    }

    public function test_available_tags_are_collected(): void
    {
        $user = $this->createCoachUser();
        $coach = $user->coach;

        $client1 = $this->createClientWithUser(['tags' => ['vip', 'morning']]);
        $client2 = $this->createClientWithUser(['tags' => ['vip', 'evening']]);

        $coach->athletes()->attach($client1->id, ['status' => 'active', 'started_at' => now()]);
        $coach->athletes()->attach($client2->id, ['status' => 'active', 'started_at' => now()]);

        $response = $this->actingAs($user)->get(route('coach.clients.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('availableTags')
            ->where('availableTags', function ($tags) {
                $tagsArray = collect($tags)->values()->all();

                return count($tagsArray) === 3
                    && in_array('vip', $tagsArray)
                    && in_array('morning', $tagsArray)
                    && in_array('evening', $tagsArray);
            })
        );
    }

    public function test_coach_can_view_client_detail(): void
    {
        $user = $this->createCoachUser();
        $coach = $user->coach;

        $client = $this->createClientWithUser(['goals' => ['lose weight'], 'fitness_level' => 'intermediate']);
        $coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);

        $response = $this->actingAs($user)->get(route('coach.clients.show', $client));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Users::Coach/Clients/Show', false)
            ->has('client')
            ->where('client.id', $client->id)
            ->where('client.fitness_level', 'intermediate')
            ->has('notes')
        );
    }

    public function test_coach_cannot_view_other_coaches_client(): void
    {
        $user = $this->createCoachUser();

        $otherCoachUser = $this->createCoachUser();
        $otherCoach = $otherCoachUser->coach;

        $client = $this->createClientWithUser();
        $otherCoach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);

        $response = $this->actingAs($user)->get(route('coach.clients.show', $client));

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $response = $this->get(route('coach.clients.index'));

        $response->assertRedirect();
    }
}
