<?php

namespace Modules\Bookings\Services\Contracts;

use Modules\Bookings\Models\Booking;

interface SmsServiceContract
{
    public function sendConfirmation(Booking $booking): void;

    public function sendCancellation(Booking $booking): void;

    public function sendReminder(Booking $booking): void;
}
