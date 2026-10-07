<?php

namespace Modules\Bookings\Services\Contracts;

use Modules\Bookings\Models\Booking;

interface CalendarServiceContract
{
    public function createEvent(Booking $booking): ?string;

    public function updateEvent(Booking $booking): void;

    public function deleteEvent(Booking $booking): void;
}
