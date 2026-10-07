<?php

namespace Modules\Bookings\Services;

use Illuminate\Support\Facades\Log;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Services\Contracts\CalendarServiceContract;

class StubCalendarService implements CalendarServiceContract
{
    public function createEvent(Booking $booking): ?string
    {
        Log::info('[Bookings Stub] CalendarService::createEvent', [
            'booking_id' => $booking->id,
            'coach_id' => $booking->coach_id,
            'starts_at' => $booking->starts_at,
        ]);

        return null;
    }

    public function updateEvent(Booking $booking): void
    {
        Log::info('[Bookings Stub] CalendarService::updateEvent', [
            'booking_id' => $booking->id,
            'google_calendar_event_id' => $booking->google_calendar_event_id,
        ]);
    }

    public function deleteEvent(Booking $booking): void
    {
        Log::info('[Bookings Stub] CalendarService::deleteEvent', [
            'booking_id' => $booking->id,
            'google_calendar_event_id' => $booking->google_calendar_event_id,
        ]);
    }
}
