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

class CoachClientNoteControllerTest extends TestCase
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

    public function test_coach_can_add_note_to_own_client(): void
    {
        $user = $this->createCoachUser();
        $client = Client::factory()->create();
        $user->coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);

        $response = $this->actingAs($user)->post(
            route('coach.clients.notes.store', $client),
            ['content' => 'Good progress today']
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('client_notes', [
            'client_id' => $client->id,
            'coach_id' => $user->coach->id,
            'content' => 'Good progress today',
        ]);
    }

    public function test_cannot_add_note_to_unlinked_client(): void
    {
        $user = $this->createCoachUser();
        $client = Client::factory()->create();

        $response = $this->actingAs($user)->post(
            route('coach.clients.notes.store', $client),
            ['content' => 'Should not save']
        );

        $response->assertForbidden();
    }

    public function test_content_is_required(): void
    {
        $user = $this->createCoachUser();
        $client = Client::factory()->create();
        $user->coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);

        $response = $this->actingAs($user)->post(
            route('coach.clients.notes.store', $client),
            ['content' => '']
        );

        $response->assertSessionHasErrors('content');
    }

    public function test_content_max_length_validation(): void
    {
        $user = $this->createCoachUser();
        $client = Client::factory()->create();
        $user->coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);

        $response = $this->actingAs($user)->post(
            route('coach.clients.notes.store', $client),
            ['content' => str_repeat('a', 5001)]
        );

        $response->assertSessionHasErrors('content');
    }
}
