<?php

namespace Modules\Bookings\Services\Contracts;

use Modules\Bookings\Models\Booking;

interface BookingEmailServiceContract
{
    public function sendConfirmation(Booking $booking): void;

    public function sendCancellation(Booking $booking, string $reason): void;

    public function sendReminder(Booking $booking): void;
}
