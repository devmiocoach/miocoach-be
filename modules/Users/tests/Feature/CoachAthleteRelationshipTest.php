<?php

namespace App\Modules\Users\Tests\Feature;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Providers\UsersServiceProvider;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachAthleteRelationshipTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->app->register(UsersServiceProvider::class);
    }

    public function test_coach_can_have_many_athletes(): void
    {
        $coach = Coach::factory()->create();
        $athletes = Client::factory()->count(3)->create();

        foreach ($athletes as $athlete) {
            $coach->athletes()->attach($athlete->id, ['status' => 'active', 'started_at' => now()]);
        }

        $this->assertCount(3, $coach->athletes);
    }

    public function test_athlete_can_have_many_coaches(): void
    {
        $athlete = Client::factory()->create();
        $coaches = Coach::factory()->count(2)->create();

        foreach ($coaches as $coach) {
            $athlete->coaches()->attach($coach->id, ['status' => 'active', 'started_at' => now()]);
        }

        $this->assertCount(2, $athlete->coaches);
    }

    public function test_pivot_stores_status_and_timestamps(): void
    {
        $coach = Coach::factory()->create();
        $athlete = Client::factory()->create();
        $startedAt = now();

        $coach->athletes()->attach($athlete->id, [
            'status' => 'pending',
            'started_at' => $startedAt,
        ]);

        $pivot = $coach->athletes()->first()->pivot;

        $this->assertEquals('pending', $pivot->status);
        $this->assertEquals($startedAt->toDateTimeString(), $pivot->started_at);
        $this->assertNull($pivot->ended_at);
    }

    public function test_active_athletes_scope_filters_by_status(): void
    {
        $coach = Coach::factory()->create();
        $activeAthlete = Client::factory()->create();
        $pendingAthlete = Client::factory()->create();
        $suspendedAthlete = Client::factory()->create();

        $coach->athletes()->attach($activeAthlete->id, ['status' => 'active']);
        $coach->athletes()->attach($pendingAthlete->id, ['status' => 'pending']);
        $coach->athletes()->attach($suspendedAthlete->id, ['status' => 'suspended']);

        $this->assertCount(1, $coach->activeAthletes);
        $this->assertTrue($coach->activeAthletes->first()->is($activeAthlete));
    }

    public function test_active_coaches_scope_filters_by_status(): void
    {
        $athlete = Client::factory()->create();
        $activeCoach = Coach::factory()->create();
        $pendingCoach = Coach::factory()->create();

        $athlete->coaches()->attach($activeCoach->id, ['status' => 'active']);
        $athlete->coaches()->attach($pendingCoach->id, ['status' => 'pending']);

        $this->assertCount(1, $athlete->activeCoaches);
        $this->assertTrue($athlete->activeCoaches->first()->is($activeCoach));
    }

    public function test_relationship_is_unique_per_pair(): void
    {
        $coach = Coach::factory()->create();
        $athlete = Client::factory()->create();

        $coach->athletes()->attach($athlete->id, ['status' => 'active']);

        $this->expectException(QueryException::class);
        $coach->athletes()->attach($athlete->id, ['status' => 'pending']);
    }

    public function test_user_coach_profile_relationship(): void
    {
        $user = User::factory()->create(['type' => UserType::COACH->value]);
        $coach = Coach::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->coach->is($coach));
        $this->assertTrue($user->isCoach());
        $this->assertFalse($user->isAthlete());
    }

    public function test_user_client_profile_relationship(): void
    {
        $user = User::factory()->create(['type' => UserType::ATLETA->value]);
        $client = Client::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->client->is($client));
        $this->assertTrue($user->isAthlete());
        $this->assertFalse($user->isCoach());
    }

    public function test_deleting_coach_cascades_to_pivot(): void
    {
        $coach = Coach::factory()->create();
        $athlete = Client::factory()->create();

        $coach->athletes()->attach($athlete->id, ['status' => 'active']);
        $this->assertDatabaseHas('athlete_coach', ['coach_id' => $coach->id]);

        $coach->forceDelete();
        $this->assertDatabaseMissing('athlete_coach', ['coach_id' => $coach->id]);
    }

    public function test_deleting_athlete_cascades_to_pivot(): void
    {
        $coach = Coach::factory()->create();
        $athlete = Client::factory()->create();

        $coach->athletes()->attach($athlete->id, ['status' => 'active']);
        $this->assertDatabaseHas('athlete_coach', ['athlete_id' => $athlete->id]);

        $athlete->forceDelete();
        $this->assertDatabaseMissing('athlete_coach', ['athlete_id' => $athlete->id]);
    }
}
