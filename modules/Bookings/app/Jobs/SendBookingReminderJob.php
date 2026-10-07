<?php

namespace Modules\Bookings\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Services\Contracts\BookingEmailServiceContract;
use Modules\Bookings\Services\Contracts\PushServiceContract;

class SendBookingReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly int $bookingId,
        private readonly string $type,
    ) {}

    public function handle(
        BookingEmailServiceContract $email,
        PushServiceContract $push,
    ): void {
        $booking = Booking::find($this->bookingId);

        if (! $booking || $booking->status !== BookingStatus::Confirmed) {
            Log::info('SendBookingReminderJob skipped', [
                'booking_id' => $this->bookingId,
                'reason' => 'not confirmed or not found',
            ]);

            return;
        }

        if ($booking->client?->user) {
            $push->send(
                $booking->client->user,
                'Promemoria sessione',
                'Hai una sessione tra '.($this->type === '24h' ? '24 ore' : '1 ora').' — '.$booking->starts_at->format('d/m/Y H:i').'.',
            );
        }

        $email->sendReminder($booking);

        if ($this->type === '1h' && $booking->coach->user) {
            $push->send(
                $booking->coach->user,
                'Promemoria sessione',
                "Hai una sessione tra 1 ora — {$booking->starts_at->format('d/m/Y H:i')}.",
            );
        }

        Log::info("SendBookingReminderJob [{$this->type}] sent", ['booking_id' => $this->bookingId]);
    }
}
