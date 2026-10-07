<?php

namespace Modules\Bookings\Services;

use Illuminate\Support\Facades\Log;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Services\Contracts\BookingEmailServiceContract;

class StubBookingEmailService implements BookingEmailServiceContract
{
    public function sendConfirmation(Booking $booking): void
    {
        Log::info('[Bookings Stub] EmailService::sendConfirmation', ['booking_id' => $booking->id]);
    }

    public function sendCancellation(Booking $booking, string $reason): void
    {
        Log::info('[Bookings Stub] EmailService::sendCancellation', ['booking_id' => $booking->id, 'reason' => $reason]);
    }

    public function sendReminder(Booking $booking): void
    {
        Log::info('[Bookings Stub] EmailService::sendReminder', ['booking_id' => $booking->id]);
    }
}
