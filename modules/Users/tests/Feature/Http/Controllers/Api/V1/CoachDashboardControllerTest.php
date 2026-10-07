<?php

namespace App\Modules\Users\Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Enums\UserType;
use App\Models\User;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachActivity;
use App\Modules\Users\Providers\UsersServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Modules\Auth\Http\Middleware\JwtAuthenticate;
use Modules\Auth\Http\Middleware\RequireVerified;
use Modules\Auth\Providers\AuthServiceProvider;
use Modules\Bookings\Enums\BookingServiceType;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Providers\BookingsServiceProvider;
use Tests\TestCase;

/**
 * Mobile dashboard endpoint. The KPI arithmetic itself is shared with the
 * Inertia dashboard (see CoachDashboardControllerTest in ../../../Http) via
 * CoachDashboardStatsService — what matters here is the JSON contract and that
 * the numbers survive the trip through the API.
 */
class CoachDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->app->register(UsersServiceProvider::class);
        $this->app->register(AuthServiceProvider::class);
        $this->app->register(BookingsServiceProvider::class);
    }

    /**
     * @return array{0: User, 1: Coach}
     */
    private function makeCoachUser(): array
    {
        $coach = Coach::factory()->create();
        $user = $coach->user;
        $user->assignRole(Role::COACH);
        $user->setRelation('coach', $coach);

        return [$user, $coach];
    }

    private function getDashboard(User $user): TestResponse
    {
        return $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->getJson('/api/v1/coaches/me/dashboard');
    }

    private function attachActiveAthlete(Coach $coach, Client $client): void
    {
        $coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);
    }

    public function test_it_returns_the_full_dashboard_payload(): void
    {
        [$user] = $this->makeCoachUser();

        $this->getDashboard($user)
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'stats' => [
                        'active_clients_count',
                        'sessions_this_week',
                        'revenue_this_month',
                        'expiring_clients_count',
                        'expiring_this_week_count',
                        'inactive_clients_count',
                    ],
                    'today_sessions',
                    'recent_activities',
                ],
            ]);
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/v1/coaches/me/dashboard')->assertUnauthorized();
    }

    public function test_a_user_without_a_coach_profile_gets_404(): void
    {
        $user = User::factory()->create(['type' => UserType::COACH]);
        $user->assignRole(Role::COACH);

        $this->getDashboard($user)->assertNotFound();
    }

    public function test_it_counts_only_active_athletes(): void
    {
        [$user, $coach] = $this->makeCoachUser();

        foreach (Client::factory()->count(3)->create() as $client) {
            $this->attachActiveAthlete($coach, $client);
        }
        $coach->athletes()->attach(
            Client::factory()->create()->id,
            ['status' => 'suspended', 'started_at' => now()->subMonth()],
        );

        $this->getDashboard($user)
            ->assertJsonPath('data.stats.active_clients_count', 3);
    }

    public function test_it_sums_only_confirmed_and_completed_bookings_of_this_month(): void
    {
        [$user, $coach] = $this->makeCoachUser();

        Booking::factory()->create([
            'coach_id' => $coach->id,
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
            'status' => BookingStatus::Completed,
            'price' => 50.00,
        ]);
        Booking::factory()->create([
            'coach_id' => $coach->id,
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
            'status' => BookingStatus::Confirmed,
            'price' => 30.00,
        ]);
        Booking::factory()->create([
            'coach_id' => $coach->id,
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
            'status' => BookingStatus::Cancelled,
            'price' => 100.00,
        ]);

        $response = $this->getDashboard($user)->assertOk();

        $this->assertSame(80.0, (float) $response->json('data.stats.revenue_this_month'));
    }

    public function test_expiring_and_inactive_counts_use_their_own_windows(): void
    {
        [$user, $coach] = $this->makeCoachUser();

        // Expires in 3 days → inside the rolling 7-day expiring window.
        $expiring = Client::factory()->create(['subscription_expires_at' => now()->addDays(3)]);
        // Expires in 30 days → in neither count.
        $safe = Client::factory()->create(['subscription_expires_at' => now()->addDays(30)]);
        // Already lapsed → past the expiring window, counted as inactive.
        $lapsed = Client::factory()->create(['subscription_expires_at' => now()->subDay()]);
        // No subscription date at all → in neither count.
        $noSubscription = Client::factory()->create(['subscription_expires_at' => null]);

        foreach ([$expiring, $safe, $lapsed, $noSubscription] as $client) {
            $this->attachActiveAthlete($coach, $client);
        }

        // Suspended link → counted as inactive regardless of its subscription.
        $coach->athletes()->attach(
            Client::factory()->create(['subscription_expires_at' => now()->addYear()])->id,
            ['status' => 'suspended', 'started_at' => now()],
        );

        $this->getDashboard($user)
            ->assertJsonPath('data.stats.expiring_clients_count', 1)
            ->assertJsonPath('data.stats.inactive_clients_count', 2);
    }

    public function test_today_sessions_carry_the_client_and_service_label(): void
    {
        [$user, $coach] = $this->makeCoachUser();
        $client = Client::factory()->create();

        Booking::factory()->create([
            'coach_id' => $coach->id,
            'client_id' => $client->id,
            'starts_at' => now()->setTime(10, 0),
            'ends_at' => now()->setTime(11, 0),
            'status' => BookingStatus::Confirmed,
            'service_type' => BookingServiceType::PersonalTraining,
        ]);
        Booking::factory()->create([
            'coach_id' => $coach->id,
            'client_id' => $client->id,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(11, 0),
            'status' => BookingStatus::Confirmed,
        ]);

        $this->getDashboard($user)
            ->assertJsonCount(1, 'data.today_sessions')
            ->assertJsonPath('data.today_sessions.0.service_type', 'personal_training')
            ->assertJsonPath('data.today_sessions.0.service_type_label', 'Personal Training')
            ->assertJsonPath('data.today_sessions.0.client.id', $client->id)
            ->assertJsonPath('data.today_sessions.0.client.name', $client->user->name);
    }

    public function test_recent_activities_are_capped_at_ten_and_newest_first(): void
    {
        [$user, $coach] = $this->makeCoachUser();

        for ($i = 0; $i < 15; $i++) {
            CoachActivity::create([
                'coach_id' => $coach->id,
                'event_type' => 'new_booking',
                'metadata' => ['client_name' => "Client {$i}"],
                'created_at' => now()->subMinutes($i),
            ]);
        }

        $this->getDashboard($user)
            ->assertJsonCount(10, 'data.recent_activities')
            ->assertJsonPath('data.recent_activities.0.event_type', 'new_booking')
            ->assertJsonPath('data.recent_activities.0.metadata.client_name', 'Client 0');
    }
}
