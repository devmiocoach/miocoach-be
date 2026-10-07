<?php

namespace Modules\Bookings\Services;

use Illuminate\Support\Facades\Log;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Services\Contracts\SmsServiceContract;

class StubSmsService implements SmsServiceContract
{
    public function sendConfirmation(Booking $booking): void
    {
        Log::info('[Bookings Stub] SmsService::sendConfirmation', [
            'booking_id' => $booking->id,
            'phone' => $booking->guest_phone ?? $booking->client?->phone,
        ]);
    }

    public function sendCancellation(Booking $booking): void
    {
        Log::info('[Bookings Stub] SmsService::sendCancellation', [
            'booking_id' => $booking->id,
        ]);
    }

    public function sendReminder(Booking $booking): void
    {
        Log::info('[Bookings Stub] SmsService::sendReminder', [
            'booking_id' => $booking->id,
            'starts_at' => $booking->starts_at,
        ]);
    }
}
