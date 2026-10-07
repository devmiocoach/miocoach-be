<?php

namespace Modules\Bookings\Tests\Feature;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Exceptions\BookingOverlapException;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Services\BookingService;
use Modules\Bookings\Services\StubBookingEmailService;
use Modules\Bookings\Services\StubCalendarService;
use Modules\Bookings\Services\StubPushService;
use Modules\Bookings\Services\StubSmsService;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $service;

    private Coach $coach;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->service = new BookingService(
            new StubCalendarService,
            new StubSmsService,
            new StubBookingEmailService,
            new StubPushService,
        );
        $this->coach = Coach::factory()->create();
    }

    public function test_check_availability_returns_true_when_no_bookings(): void
    {
        $result = $this->service->checkAvailability(
            $this->coach,
            Carbon::parse('2026-08-01 10:00:00'),
            Carbon::parse('2026-08-01 11:00:00'),
        );

        $this->assertTrue($result);
    }

    public function test_check_availability_returns_false_on_overlap(): void
    {
        Booking::factory()->create([
            'coach_id' => $this->coach->id,
            'starts_at' => '2026-08-01 10:00:00',
            'ends_at' => '2026-08-01 11:00:00',
            'status' => BookingStatus::Confirmed->value,
        ]);

        $result = $this->service->checkAvailability(
            $this->coach,
            Carbon::parse('2026-08-01 10:30:00'),
            Carbon::parse('2026-08-01 11:30:00'),
        );

        $this->assertFalse($result);
    }

    public function test_check_availability_ignores_cancelled(): void
    {
        Booking::factory()->cancelled()->create([
            'coach_id' => $this->coach->id,
            'starts_at' => '2026-08-01 10:00:00',
            'ends_at' => '2026-08-01 11:00:00',
        ]);

        $result = $this->service->checkAvailability(
            $this->coach,
            Carbon::parse('2026-08-01 10:00:00'),
            Carbon::parse('2026-08-01 11:00:00'),
        );

        $this->assertTrue($result);
    }

    public function test_create_for_coach_creates_confirmed_booking(): void
    {
        $client = Client::factory()->create();

        $booking = $this->service->createForCoach($this->coach, $client, [
            'starts_at' => '2026-08-01 10:00:00',
            'ends_at' => '2026-08-01 11:00:00',
            'service_type' => 'personal_training',
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'coach_id' => $this->coach->id,
            'client_id' => $client->id,
            'status' => 'confirmed',
            'source' => 'manual',
            'service_type' => 'personal_training',
        ]);
    }

    public function test_create_for_coach_throws_on_overlap(): void
    {
        $client = Client::factory()->create();

        Booking::factory()->create([
            'coach_id' => $this->coach->id,
            'starts_at' => '2026-08-01 10:00:00',
            'ends_at' => '2026-08-01 11:00:00',
            'status' => BookingStatus::Confirmed->value,
        ]);

        $this->expectException(BookingOverlapException::class);

        $this->service->createForCoach($this->coach, $client, [
            'starts_at' => '2026-08-01 10:30:00',
            'ends_at' => '2026-08-01 11:30:00',
            'service_type' => 'personal_training',
        ]);
    }

    public function test_cancel_sets_cancelled_status_and_reason(): void
    {
        $booking = Booking::factory()->create([
            'coach_id' => $this->coach->id,
            'status' => BookingStatus::Confirmed->value,
        ]);

        $this->service->cancel($booking, 'Indisponibilità', 'coach');

        $booking->refresh();
        $this->assertEquals(BookingStatus::Cancelled, $booking->status);
        $this->assertEquals('Indisponibilità', $booking->cancellation_reason);
        $this->assertNotNull($booking->cancelled_at);
    }

    public function test_complete_sets_completed_status(): void
    {
        $booking = Booking::factory()->create([
            'coach_id' => $this->coach->id,
            'status' => BookingStatus::Confirmed->value,
        ]);

        $this->service->complete($booking);

        $this->assertEquals(BookingStatus::Completed, $booking->fresh()->status);
    }

    public function test_reschedule_updates_times(): void
    {
        $booking = Booking::factory()->create([
            'coach_id' => $this->coach->id,
            'starts_at' => '2026-08-01 10:00:00',
            'ends_at' => '2026-08-01 11:00:00',
            'status' => BookingStatus::Confirmed->value,
        ]);

        $this->service->reschedule($booking, [
            'starts_at' => '2026-08-02 14:00:00',
            'ends_at' => '2026-08-02 15:00:00',
        ]);

        $booking->refresh();
        $this->assertEquals('2026-08-02 14:00:00', $booking->starts_at->format('Y-m-d H:i:s'));
    }
}
