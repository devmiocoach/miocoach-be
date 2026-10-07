<?php

namespace Modules\Bookings\Tests\Feature;

use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Auth\Services\JwtService;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Models\Booking;
use Tests\TestCase;

class PublicBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        $publicKey = openssl_pkey_get_details($key)['key'];

        $this->app->forgetInstance(JwtService::class);
        config([
            'auth.modules.jwt.private_key' => $privateKey,
            'auth.modules.jwt.public_key' => $publicKey,
        ]);
    }

    public function test_public_can_create_booking(): void
    {
        $coach = Coach::factory()->create();

        $response = $this->postJson('/api/v1/bookings/public', [
            'coach_id' => $coach->id,
            'slot_starts_at' => '2026-09-01 10:00:00',
            'service_type' => 'personal_training',
            'guest_name' => 'Mario Rossi',
            'guest_email' => 'mario@example.com',
            'guest_phone' => '+39 333 1234567',
            'duration_minutes' => 60,
        ]);

        $response->assertCreated()->assertJsonStructure(['data' => ['id', 'status', 'starts_at']]);
        $this->assertDatabaseHas('bookings', [
            'coach_id' => $coach->id,
            'guest_email' => 'mario@example.com',
            'status' => 'confirmed',
            'source' => 'public_web',
        ]);

        $booking = Booking::where('guest_email', 'mario@example.com')->first();
        $this->assertNotNull($booking->cancellation_token);
    }

    public function test_public_booking_creates_jwt_cancellation_token(): void
    {
        $coach = Coach::factory()->create();

        $this->postJson('/api/v1/bookings/public', [
            'coach_id' => $coach->id,
            'slot_starts_at' => '2026-09-01 10:00:00',
            'service_type' => 'personal_training',
            'guest_name' => 'Mario Rossi',
            'guest_email' => 'mario@example.com',
            'duration_minutes' => 60,
        ])->assertCreated();

        $booking = Booking::where('guest_email', 'mario@example.com')->firstOrFail();
        $this->assertSame(2, substr_count($booking->cancellation_token, '.'));

        $payload = app(JwtService::class)->decode($booking->cancellation_token);
        $this->assertSame('booking_cancellation', $payload->purpose);
        $this->assertSame($booking->id, $payload->booking_id);
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $payload->exp, 60);
    }

    public function test_public_booking_sends_confirmation_email_with_ics_calendar_and_cancel_links(): void
    {
        Mail::fake();

        $coach = Coach::factory()->create();

        $this->postJson('/api/v1/bookings/public', [
            'coach_id' => $coach->id,
            'slot_starts_at' => '2026-09-01 10:00:00',
            'service_type' => 'personal_training',
            'guest_name' => 'Mario Rossi',
            'guest_email' => 'mario@example.com',
            'duration_minutes' => 60,
        ])->assertCreated();

        Mail::assertSent('Modules\Bookings\Mail\BookingConfirmationMail', function ($mail) {
            $rendered = $mail->render();

            return $mail->hasTo('mario@example.com')
                && str_contains($rendered, 'Aggiungi a Google Calendar')
                && str_contains($rendered, '/calendar.ics')
                && str_contains($rendered, 'Cancella')
                && collect($mail->attachments())->contains(fn ($attachment) => str_ends_with($attachment->as, '.ics'));
        });
    }

    public function test_public_booking_calendar_ics_can_be_downloaded_with_valid_token(): void
    {
        $coach = Coach::factory()->create();
        $booking = Booking::factory()->create([
            'coach_id' => $coach->id,
            'starts_at' => '2026-09-01 10:00:00',
            'ends_at' => '2026-09-01 11:00:00',
            'status' => BookingStatus::Confirmed->value,
        ]);

        $booking->forceFill([
            'cancellation_token' => app(JwtService::class)->generateBookingCancellationToken($booking->id),
            'cancellation_token_expires_at' => now()->addDays(30),
        ])->save();

        $response = $this->get("/api/v1/bookings/public/{$booking->cancellation_token}/calendar.ics");

        $response->assertOk();
        $response->assertHeader('content-type', 'text/calendar; charset=UTF-8');
        $this->assertStringContainsString('BEGIN:VCALENDAR', $response->getContent());
        $this->assertStringContainsString("booking-{$booking->id}", $response->getContent());
    }

    public function test_public_booking_returns_422_on_overlap(): void
    {
        $coach = Coach::factory()->create();

        Booking::factory()->create([
            'coach_id' => $coach->id,
            'starts_at' => '2026-09-01 10:00:00',
            'ends_at' => '2026-09-01 11:00:00',
            'status' => BookingStatus::Confirmed->value,
        ]);

        $response = $this->postJson('/api/v1/bookings/public', [
            'coach_id' => $coach->id,
            'slot_starts_at' => '2026-09-01 10:30:00',
            'service_type' => 'personal_training',
            'guest_name' => 'Mario Rossi',
            'guest_email' => 'mario@example.com',
            'duration_minutes' => 60,
        ]);

        $response->assertUnprocessable();
    }

    public function test_can_cancel_with_valid_token_inside_window(): void
    {
        $coach = Coach::factory()->create(['cancellation_window_hours' => 24]);

        $booking = Booking::factory()->create([
            'coach_id' => $coach->id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHour(),
            'status' => BookingStatus::Confirmed->value,
        ]);

        $booking->forceFill([
            'cancellation_token' => app(JwtService::class)->generateBookingCancellationToken($booking->id),
            'cancellation_token_expires_at' => now()->addDays(30),
        ])->save();

        $response = $this->deleteJson("/api/v1/bookings/public/{$booking->cancellation_token}");

        $response->assertOk()->assertJsonStructure(['message']);
        $this->assertNull($booking->fresh()->cancellation_token);
        $this->deleteJson("/api/v1/bookings/public/{$booking->cancellation_token}")
            ->assertNotFound();
    }

    public function test_cancel_outside_policy_window_returns_422(): void
    {
        $coach = Coach::factory()->create(['cancellation_window_hours' => 48]);

        $booking = Booking::factory()->create([
            'coach_id' => $coach->id,
            'starts_at' => now()->addHours(24),
            'ends_at' => now()->addHours(25),
            'status' => BookingStatus::Confirmed->value,
        ]);

        $booking->forceFill([
            'cancellation_token' => app(JwtService::class)->generateBookingCancellationToken($booking->id),
            'cancellation_token_expires_at' => now()->addDays(30),
        ])->save();

        $response = $this->deleteJson("/api/v1/bookings/public/{$booking->cancellation_token}");

        $response->assertUnprocessable()->assertJsonStructure(['message']);
    }

    public function test_cancel_with_invalid_token_returns_404(): void
    {
        $response = $this->deleteJson('/api/v1/bookings/public/nonexistent-token');

        $response->assertNotFound();
    }
}
