<?php

namespace App\Modules\Users\Tests\Feature\Http;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachActivity;
use App\Modules\Users\Providers\UsersServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Providers\AuthServiceProvider;
use Modules\Bookings\Enums\BookingServiceType;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Providers\BookingsServiceProvider;
use Tests\TestCase;

class CoachDashboardControllerTest extends TestCase
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

    public function test_coach_can_view_dashboard(): void
    {
        $user = $this->createCoachUser();

        $response = $this->actingAs($user)->get(route('coach.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Users::Coach/Dashboard', false)
            ->has('activeClientsCount')
            ->has('sessionsThisWeek')
            ->has('revenueThisMonth')
            ->has('expiringClientsCount')
            ->has('todaySessions')
            ->has('expiringThisWeekCount')
            ->has('inactiveClientsCount')
            ->has('recentActivities')
        );
    }

    public function test_active_clients_count_is_correct(): void
    {
        $user = $this->createCoachUser();
        $coach = $user->coach;

        $activeClients = Client::factory()->count(3)->create();
        $inactiveClient = Client::factory()->create();

        foreach ($activeClients as $client) {
            $coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);
        }
        $coach->athletes()->attach($inactiveClient->id, ['status' => 'suspended', 'started_at' => now()->subMonth()]);

        $response = $this->actingAs($user)->get(route('coach.dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('activeClientsCount', 3)
        );
    }

    public function test_sessions_this_week_count(): void
    {
        $user = $this->createCoachUser();
        $coach = $user->coach;

        // Booking this week
        Booking::factory()->create([
            'coach_id' => $coach->id,
            'starts_at' => now()->startOfWeek()->addDay(),
            'ends_at' => now()->startOfWeek()->addDay()->addHour(),
            'status' => BookingStatus::Confirmed,
        ]);

        // Booking last week (should not count)
        Booking::factory()->create([
            'coach_id' => $coach->id,
            'starts_at' => now()->subWeek(),
            'ends_at' => now()->subWeek()->addHour(),
            'status' => BookingStatus::Confirmed,
        ]);

        $response = $this->actingAs($user)->get(route('coach.dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('sessionsThisWeek', 1)
        );
    }

    public function test_revenue_this_month(): void
    {
        $user = $this->createCoachUser();
        $coach = $user->coach;

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

        // Cancelled (should not count)
        Booking::factory()->create([
            'coach_id' => $coach->id,
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
            'status' => BookingStatus::Cancelled,
            'price' => 100.00,
        ]);

        $response = $this->actingAs($user)->get(route('coach.dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('revenueThisMonth', fn ($value) => (float) $value === 80.0)
        );
    }

    public function test_expiring_clients_count(): void
    {
        $user = $this->createCoachUser();
        $coach = $user->coach;

        $expiringClient = Client::factory()->create([
            'subscription_expires_at' => now()->addDays(3),
        ]);
        $safeClient = Client::factory()->create([
            'subscription_expires_at' => now()->addDays(30),
        ]);
        $expiredClient = Client::factory()->create([
            'subscription_expires_at' => now()->subDay(),
        ]);

        $coach->athletes()->attach($expiringClient->id, ['status' => 'active', 'started_at' => now()]);
        $coach->athletes()->attach($safeClient->id, ['status' => 'active', 'started_at' => now()]);
        $coach->athletes()->attach($expiredClient->id, ['status' => 'active', 'started_at' => now()]);

        $response = $this->actingAs($user)->get(route('coach.dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('expiringClientsCount', 1)
        );
    }

    public function test_today_sessions_returns_only_today(): void
    {
        $user = $this->createCoachUser();
        $coach = $user->coach;
        $client = Client::factory()->create();

        // Today
        Booking::factory()->create([
            'coach_id' => $coach->id,
            'client_id' => $client->id,
            'starts_at' => now()->setTime(10, 0),
            'ends_at' => now()->setTime(11, 0),
            'status' => BookingStatus::Confirmed,
            'service_type' => BookingServiceType::PersonalTraining,
        ]);

        // Tomorrow
        Booking::factory()->create([
            'coach_id' => $coach->id,
            'client_id' => $client->id,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(11, 0),
            'status' => BookingStatus::Confirmed,
        ]);

        $response = $this->actingAs($user)->get(route('coach.dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->has('todaySessions', 1)
            ->where('todaySessions.0.service_type', 'personal_training')
        );
    }

    public function test_recent_activities_returns_last_10(): void
    {
        $user = $this->createCoachUser();
        $coach = $user->coach;

        for ($i = 0; $i < 15; $i++) {
            CoachActivity::create([
                'coach_id' => $coach->id,
                'event_type' => 'new_booking',
                'metadata' => ['client_name' => "Client {$i}"],
                'created_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->actingAs($user)->get(route('coach.dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->has('recentActivities', 10)
        );
    }

    public function test_inactive_clients_count_includes_suspended_and_expired(): void
    {
        $user = $this->createCoachUser();
        $coach = $user->coach;

        // Active with future subscription — should NOT count
        $activeClient = Client::factory()->create([
            'subscription_expires_at' => now()->addDays(10),
        ]);

        // Active with expired subscription — should count
        $expiredClient = Client::factory()->create([
            'subscription_expires_at' => now()->subDay(),
        ]);

        // Suspended — should count
        $suspendedClient = Client::factory()->create();

        // Active with no subscription date — should NOT count
        $noSubClient = Client::factory()->create([
            'subscription_expires_at' => null,
        ]);

        $coach->athletes()->attach($activeClient->id, ['status' => 'active', 'started_at' => now()]);
        $coach->athletes()->attach($expiredClient->id, ['status' => 'active', 'started_at' => now()]);
        $coach->athletes()->attach($suspendedClient->id, ['status' => 'suspended', 'started_at' => now()]);
        $coach->athletes()->attach($noSubClient->id, ['status' => 'active', 'started_at' => now()]);

        $response = $this->actingAs($user)->get(route('coach.dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('inactiveClientsCount', 2)
        );
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $response = $this->get(route('coach.dashboard'));

        $response->assertRedirect();
    }
}
