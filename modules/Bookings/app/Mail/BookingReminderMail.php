<?php

namespace Modules\Bookings\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Services\BookingCalendarFile;

class BookingReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Promemoria sessione',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'bookings::emails.reminder',
            with: [
                'booking' => $this->booking,
                'coachName' => $this->booking->coach?->user?->name ?? 'Il tuo coach',
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => app(BookingCalendarFile::class)->contents($this->booking), "booking-{$this->booking->id}.ics")
                ->withMime('text/calendar'),
        ];
    }
}
