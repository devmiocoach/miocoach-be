<?php

namespace Modules\Bookings\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Bookings\Models\Booking;

class BookingCancellationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Prenotazione cancellata',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'bookings::emails.cancellation',
            with: [
                'booking' => $this->booking,
                'coachName' => $this->booking->coach?->user?->name ?? 'Il tuo coach',
                'reason' => $this->reason,
            ],
        );
    }
}
