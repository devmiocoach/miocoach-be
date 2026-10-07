<?php

namespace App\Modules\Users\Tests\Feature\Http;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Providers\UsersServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Providers\AuthServiceProvider;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Providers\BookingsServiceProvider;
use Tests\TestCase;

class CoachBookingStatusControllerTest extends TestCase
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

    public function test_coach_can_mark_booking_as_completed(): void
    {
        $user = $this->createCoachUser();
        $booking = Booking::factory()->create([
            'coach_id' => $user->coach->id,
            'status' => BookingStatus::Confirmed,
        ]);

        $response = $this->actingAs($user)->patch(route('coach.bookings.complete', $booking));

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Completed->value,
        ]);
    }

    public function test_cannot_complete_already_completed_booking(): void
    {
        $user = $this->createCoachUser();
        $booking = Booking::factory()->create([
            'coach_id' => $user->coach->id,
            'status' => BookingStatus::Completed,
        ]);

        $response = $this->actingAs($user)->patch(route('coach.bookings.complete', $booking));

        $response->assertStatus(422);
    }

    public function test_cannot_complete_another_coachs_booking(): void
    {
        $user = $this->createCoachUser();
        $otherCoach = Coach::factory()->create();

        $booking = Booking::factory()->create([
            'coach_id' => $otherCoach->id,
            'status' => BookingStatus::Confirmed,
        ]);

        $response = $this->actingAs($user)->patch(route('coach.bookings.complete', $booking));

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $booking = Booking::factory()->create(['status' => BookingStatus::Confirmed]);

        $response = $this->patch(route('coach.bookings.complete', $booking));

        $response->assertRedirect();
    }
}
