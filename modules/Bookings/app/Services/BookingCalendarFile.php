<?php

namespace Modules\Bookings\Services;

use Modules\Bookings\Models\Booking;

class BookingCalendarFile
{
    public function url(Booking $booking): string
    {
        return rtrim(config('app.url'), '/')."/api/v1/bookings/public/{$booking->cancellation_token}/calendar.ics";
    }

    public function contents(Booking $booking): string
    {
        $uid = "booking-{$booking->id}@".parse_url(config('app.url'), PHP_URL_HOST);
        $summary = 'Sessione con '.($booking->coach?->user?->name ?? 'coach');

        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Miocoach//Bookings//IT',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$booking->starts_at->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$booking->ends_at->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escape($summary),
            'DESCRIPTION:'.$this->escape("Prenotazione #{$booking->id}"),
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', "\n", "\r", ',', ';'], ['\\\\', '\\n', '', '\,', '\;'], $value);
    }
}
