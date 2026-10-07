<?php

namespace Modules\Bookings\Tests\Feature;

use App\Enums\Role;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachAvailability;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Http\Middleware\JwtAuthenticate;
use Modules\Auth\Http\Middleware\RequireVerified;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Models\Booking;
use Tests\TestCase;

class AvailabilitySlotsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCoach(): array
    {
        $coach = Coach::factory()->create();
        $user = $coach->user;
        $user->assignRole(Role::COACH);
        $user->setRelation('coach', $coach);

        return [$user, $coach];
    }

    public function test_returns_slots_for_coach_with_availability(): void
    {
        [$coachUser, $coach] = $this->makeCoach();
        $nextMonday = Carbon::now()->next('Monday');

        CoachAvailability::create([
            'coach_id' => $coach->id,
            'day_of_week' => (int) $nextMonday->format('w'),
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 60,
            'is_recurring' => true,
        ]);

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coachUser)
            ->getJson('/api/v1/coaches/me/availability/slots?date='.$nextMonday->format('Y-m-d'));

        $response->assertOk()
            ->assertJsonStructure(['data' => [['starts_at', 'ends_at', 'duration_minutes', 'available']]])
            ->assertJsonCount(2, 'data');
    }

    public function test_returns_empty_when_no_availability(): void
    {
        [$coachUser] = $this->makeCoach();

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coachUser)
            ->getJson('/api/v1/coaches/me/availability/slots?date=2026-08-03');

        $response->assertOk()->assertJson(['data' => []]);
    }

    public function test_occupied_slot_is_marked_unavailable(): void
    {
        [$coachUser, $coach] = $this->makeCoach();
        $nextMonday = Carbon::now()->next('Monday');

        CoachAvailability::create([
            'coach_id' => $coach->id,
            'day_of_week' => (int) $nextMonday->format('w'),
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'duration_minutes' => 60,
            'is_recurring' => true,
        ]);

        Booking::factory()->create([
            'coach_id' => $coach->id,
            'starts_at' => $nextMonday->copy()->setTime(9, 0),
            'ends_at' => $nextMonday->copy()->setTime(10, 0),
            'status' => BookingStatus::Confirmed->value,
        ]);

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coachUser)
            ->getJson('/api/v1/coaches/me/availability/slots?date='.$nextMonday->format('Y-m-d'));

        $response->assertOk();
        $this->assertFalse($response->json('data.0.available'));
    }
}
