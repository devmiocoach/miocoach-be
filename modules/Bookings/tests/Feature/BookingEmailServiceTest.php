<?php

namespace Modules\Bookings\Tests\Feature;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Services\BookingEmailService;
use Tests\TestCase;

class BookingEmailServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_cancellation_sends_email_to_booking_recipient_with_reason(): void
    {
        Mail::fake();

        $booking = Booking::factory()->forGuest()->create([
            'guest_email' => 'guest@example.com',
            'guest_name' => 'Mario Rossi',
        ]);

        app(BookingEmailService::class)->sendCancellation($booking, 'Coach non disponibile');

        Mail::assertSent('Modules\Bookings\Mail\BookingCancellationMail', function ($mail) {
            return $mail->hasTo('guest@example.com')
                && str_contains($mail->render(), 'Coach non disponibile')
                && str_contains($mail->render(), 'Prenotazione cancellata');
        });
    }

    public function test_send_reminder_sends_email_to_client_with_ics_attachment(): void
    {
        Mail::fake();

        $coach = Coach::factory()->create();
        $client = Client::factory()->create();
        $client->user->forceFill(['email' => 'client@example.com'])->save();

        $booking = Booking::factory()->create([
            'coach_id' => $coach->id,
            'client_id' => $client->id,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(11, 0),
        ]);

        app(BookingEmailService::class)->sendReminder($booking);

        Mail::assertSent('Modules\Bookings\Mail\BookingReminderMail', function ($mail) {
            return $mail->hasTo('client@example.com')
                && str_contains($mail->render(), 'Promemoria sessione')
                && collect($mail->attachments())->contains(fn ($attachment) => str_ends_with($attachment->as, '.ics'));
        });
    }
}
