<?php

namespace App\Modules\Users\Tests\Feature\Observers;

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

class BookingActivityObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->app->register(UsersServiceProvider::class);
        $this->app->register(AuthServiceProvider::class);
        $this->app->register(BookingsServiceProvider::class);
    }

    public function test_new_booking_creates_activity(): void
    {
        $coach = Coach::factory()->create();

        $booking = Booking::factory()->create([
            'coach_id' => $coach->id,
            'status' => BookingStatus::Confirmed,
            'service_type' => BookingServiceType::PersonalTraining,
        ]);

        $this->assertDatabaseHas('coach_activities', [
            'coach_id' => $coach->id,
            'event_type' => 'new_booking',
            'subject_type' => Booking::class,
            'subject_id' => $booking->id,
        ]);
    }

    public function test_completed_booking_creates_session_completed_activity(): void
    {
        $coach = Coach::factory()->create();

        $booking = Booking::factory()->create([
            'coach_id' => $coach->id,
            'status' => BookingStatus::Confirmed,
            'price' => 0,
        ]);

        // Clear the "new_booking" activity count
        $initialCount = CoachActivity::where('coach_id', $coach->id)->count();

        $booking->update(['status' => BookingStatus::Completed]);

        $this->assertDatabaseHas('coach_activities', [
            'coach_id' => $coach->id,
            'event_type' => 'session_completed',
            'subject_id' => $booking->id,
        ]);
    }

    public function test_completed_booking_with_price_creates_payment_received_activity(): void
    {
        $coach = Coach::factory()->create();

        $booking = Booking::factory()->create([
            'coach_id' => $coach->id,
            'status' => BookingStatus::Confirmed,
            'price' => 50.00,
        ]);

        $booking->update(['status' => BookingStatus::Completed]);

        $this->assertDatabaseHas('coach_activities', [
            'coach_id' => $coach->id,
            'event_type' => 'payment_received',
            'subject_id' => $booking->id,
        ]);
    }

    public function test_non_completion_status_change_does_not_create_activity(): void
    {
        $coach = Coach::factory()->create();

        $booking = Booking::factory()->create([
            'coach_id' => $coach->id,
            'status' => BookingStatus::Confirmed,
        ]);

        $countBefore = CoachActivity::where('coach_id', $coach->id)->count();

        $booking->update(['status' => BookingStatus::Cancelled]);

        $countAfter = CoachActivity::where('coach_id', $coach->id)
            ->where('event_type', 'session_completed')
            ->count();

        $this->assertEquals(0, $countAfter);
    }
}
