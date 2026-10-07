<?php

namespace Modules\Bookings\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Bookings\Mail\BookingCancellationMail;
use Modules\Bookings\Mail\BookingConfirmationMail;
use Modules\Bookings\Mail\BookingReminderMail;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Services\Contracts\BookingEmailServiceContract;

class BookingEmailService implements BookingEmailServiceContract
{
    public function sendConfirmation(Booking $booking): void
    {
        if (! $recipient = $this->recipient($booking)) {
            Log::info('[Bookings Email] Confirmation skipped: missing recipient', ['booking_id' => $booking->id]);

            return;
        }

        Mail::to($recipient)->send(new BookingConfirmationMail($booking));
    }

    public function sendCancellation(Booking $booking, string $reason): void
    {
        if (! $recipient = $this->recipient($booking)) {
            Log::info('[Bookings Email] Cancellation skipped: missing recipient', ['booking_id' => $booking->id]);

            return;
        }

        Mail::to($recipient)->send(new BookingCancellationMail($booking, $reason));
    }

    public function sendReminder(Booking $booking): void
    {
        if (! $recipient = $this->recipient($booking)) {
            Log::info('[Bookings Email] Reminder skipped: missing recipient', ['booking_id' => $booking->id]);

            return;
        }

        Mail::to($recipient)->send(new BookingReminderMail($booking));
    }

    private function recipient(Booking $booking): ?string
    {
        return $booking->guest_email ?? $booking->client?->user?->email;
    }
}
