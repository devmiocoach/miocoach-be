<?php

namespace Modules\Bookings\Tests\Feature;

use App\Enums\Role;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Auth\Http\Middleware\JwtAuthenticate;
use Modules\Auth\Http\Middleware\RequireVerified;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Models\Booking;
use Tests\TestCase;

class CoachBookingTest extends TestCase
{
    use RefreshDatabase;

    private Coach $coach;

    private $coachUser;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->coach = Coach::factory()->create();
        $this->coachUser = $this->coach->user;
        $this->coachUser->assignRole(Role::COACH);
        $this->coachUser->setRelation('coach', $this->coach);
    }

    public function test_coach_can_list_own_bookings(): void
    {
        $client = Client::factory()->create();
        $this->coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);
        Booking::factory()->count(3)->create(['coach_id' => $this->coach->id, 'client_id' => $client->id]);

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($this->coachUser)
            ->getJson('/api/v1/coaches/me/bookings');

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_coach_can_create_booking_for_client(): void
    {
        $client = Client::factory()->create();
        $this->coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($this->coachUser)
            ->postJson('/api/v1/coaches/me/bookings', [
                'client_id' => $client->id,
                'starts_at' => '2026-09-01 10:00:00',
                'ends_at' => '2026-09-01 11:00:00',
                'service_type' => 'personal_training',
            ]);

        $response->assertCreated()->assertJsonStructure(['data' => ['id', 'status', 'starts_at']]);
        $this->assertDatabaseHas('bookings', [
            'coach_id' => $this->coach->id,
            'client_id' => $client->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_create_booking_returns_422_on_overlap(): void
    {
        $client = Client::factory()->create();
        $this->coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);

        Booking::factory()->create([
            'coach_id' => $this->coach->id,
            'starts_at' => '2026-09-01 10:00:00',
            'ends_at' => '2026-09-01 11:00:00',
            'status' => BookingStatus::Confirmed->value,
        ]);

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($this->coachUser)
            ->postJson('/api/v1/coaches/me/bookings', [
                'client_id' => $client->id,
                'starts_at' => '2026-09-01 10:30:00',
                'ends_at' => '2026-09-01 11:30:00',
                'service_type' => 'personal_training',
            ]);

        $response->assertUnprocessable();
    }

    public function test_coach_can_cancel_booking(): void
    {
        $booking = Booking::factory()->create([
            'coach_id' => $this->coach->id,
            'status' => BookingStatus::Confirmed->value,
        ]);

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($this->coachUser)
            ->deleteJson("/api/v1/coaches/me/bookings/{$booking->id}", [
                'reason' => 'Indisponibilità improvvisa',
            ]);

        $response->assertNoContent();
        $this->assertEquals(BookingStatus::Cancelled->value, $booking->fresh()->status->value);
    }

    public function test_coach_cannot_cancel_another_coachs_booking(): void
    {
        $otherCoach = Coach::factory()->create();
        $booking = Booking::factory()->create([
            'coach_id' => $otherCoach->id,
            'status' => BookingStatus::Confirmed->value,
        ]);

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($this->coachUser)
            ->deleteJson("/api/v1/coaches/me/bookings/{$booking->id}", [
                'reason' => 'Test',
            ]);

        $response->assertForbidden();
    }

    public function test_coach_can_complete_booking(): void
    {
        $booking = Booking::factory()->create([
            'coach_id' => $this->coach->id,
            'status' => BookingStatus::Confirmed->value,
        ]);

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($this->coachUser)
            ->putJson("/api/v1/coaches/me/bookings/{$booking->id}", [
                'status' => 'completed',
            ]);

        $response->assertOk();
        $this->assertEquals(BookingStatus::Completed->value, $booking->fresh()->status->value);
    }
}
