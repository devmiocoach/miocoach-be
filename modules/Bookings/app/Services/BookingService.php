<?php

namespace Modules\Bookings\Services;

use App\Models\User;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Auth\Services\JwtService;
use Modules\Bookings\Enums\BookingCancelledBy;
use Modules\Bookings\Enums\BookingSource;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Exceptions\BookingOverlapException;
use Modules\Bookings\Jobs\SendBookingReminderJob;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Services\Contracts\BookingEmailServiceContract;
use Modules\Bookings\Services\Contracts\CalendarServiceContract;
use Modules\Bookings\Services\Contracts\PushServiceContract;
use Modules\Bookings\Services\Contracts\SmsServiceContract;

class BookingService
{
    public function __construct(
        private readonly CalendarServiceContract $calendar,
        private readonly SmsServiceContract $sms,
        private readonly BookingEmailServiceContract $email,
        private readonly PushServiceContract $push,
    ) {}

    public function checkAvailability(
        Coach $coach,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $excludeBookingId = null,
    ): bool {
        $query = Booking::where('coach_id', $coach->id)
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Pending->value])
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);

        if ($excludeBookingId !== null) {
            $query->where('id', '!=', $excludeBookingId);
        }

        return $query->doesntExist();
    }

    /**
     * @return array<int, array{starts_at: string, ends_at: string, duration_minutes: int, available: bool}>
     */
    public function getAvailableSlots(Coach $coach, Carbon $date, ?int $durationMinutes = null): array
    {
        $dayOfWeek = (int) $date->format('w');
        $availabilities = $coach->availabilities()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_recurring', true)
            ->get();

        $slots = [];

        foreach ($availabilities as $availability) {
            $duration = $durationMinutes ?? $availability->duration_minutes;
            $slotStart = Carbon::parse($date->toDateString().' '.$availability->start_time);
            $slotEnd = Carbon::parse($date->toDateString().' '.$availability->end_time);

            while ($slotStart->copy()->addMinutes($duration)->lte($slotEnd)) {
                $thisEnd = $slotStart->copy()->addMinutes($duration);

                $slots[] = [
                    'starts_at' => $slotStart->toISOString(),
                    'ends_at' => $thisEnd->toISOString(),
                    'duration_minutes' => $duration,
                    'available' => $this->checkAvailability($coach, $slotStart->copy(), $thisEnd->copy()),
                ];

                $slotStart->addMinutes($duration);
            }
        }

        return $slots;
    }

    public function createForCoach(Coach $coach, Client $client, array $data): Booking
    {
        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt = Carbon::parse($data['ends_at']);

        if (! $this->checkAvailability($coach, $startsAt, $endsAt)) {
            throw new BookingOverlapException;
        }

        $booking = Booking::create([
            'coach_id' => $coach->id,
            'client_id' => $client->id,
            'package_id' => $data['package_id'] ?? null,
            'service_type' => $data['service_type'],
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'duration_minutes' => (int) $startsAt->diffInMinutes($endsAt),
            'status' => BookingStatus::Confirmed->value,
            'source' => BookingSource::Manual->value,
            'notes' => $data['notes'] ?? null,
            'price' => $data['price'] ?? null,
        ]);

        $this->dispatchReminderJobs($booking);

        $calendarEventId = $this->calendar->createEvent($booking);
        if ($calendarEventId) {
            $booking->update(['google_calendar_event_id' => $calendarEventId]);
        }

        if ($client->user) {
            $this->push->send(
                $client->user,
                'Nuova sessione prenotata',
                "Il tuo coach ha prenotato una sessione per il {$booking->starts_at->format('d/m/Y H:i')}.",
            );
        }

        Log::info('BOOKING_CREATED', ['booking_id' => $booking->id, 'source' => 'manual']);

        return $booking;
    }

    public function createPublic(array $data): Booking
    {
        $coach = Coach::findOrFail($data['coach_id']);
        $startsAt = Carbon::parse($data['slot_starts_at']);
        $duration = (int) ($data['duration_minutes'] ?? 60);
        $endsAt = $startsAt->copy()->addMinutes($duration);

        if (! $this->checkAvailability($coach, $startsAt, $endsAt)) {
            throw new BookingOverlapException;
        }

        $client = null;
        if (! empty($data['guest_email'])) {
            $user = User::where('email', $data['guest_email'])->first();
            $client = $user?->client ?? null;
        }

        $booking = Booking::create([
            'coach_id' => $coach->id,
            'client_id' => $client?->id,
            'package_id' => $data['package_id'] ?? null,
            'guest_name' => $data['guest_name'],
            'guest_email' => $data['guest_email'],
            'guest_phone' => $data['guest_phone'] ?? null,
            'service_type' => $data['service_type'],
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'duration_minutes' => $duration,
            'status' => BookingStatus::Confirmed->value,
            'source' => BookingSource::PublicWeb->value,
            'cancellation_token_expires_at' => now()->addDays(30),
        ]);

        $booking->forceFill([
            'cancellation_token' => $this->generateCancellationToken($booking),
        ])->save();

        $this->email->sendConfirmation($booking);
        $this->sms->sendConfirmation($booking);

        $calendarEventId = $this->calendar->createEvent($booking);
        if ($calendarEventId) {
            $booking->update(['google_calendar_event_id' => $calendarEventId]);
        }

        if ($coach->user) {
            $this->push->send(
                $coach->user,
                'Nuova prenotazione',
                "Nuova prenotazione da {$booking->guest_name} per il {$booking->starts_at->format('d/m/Y H:i')}.",
            );
        }

        $this->dispatchReminderJobs($booking);

        Log::info('BOOKING_CREATED', ['booking_id' => $booking->id, 'source' => 'public_web']);

        return $booking;
    }

    public function cancel(Booking $booking, string $reason, string $cancelledBy): void
    {
        $booking->update([
            'status' => BookingStatus::Cancelled->value,
            'cancellation_reason' => $reason,
            'cancelled_at' => now(),
            'cancelled_by' => $cancelledBy,
        ]);

        $this->calendar->deleteEvent($booking);

        if ($cancelledBy === BookingCancelledBy::Coach->value) {
            if ($booking->client?->user) {
                $this->push->send(
                    $booking->client->user,
                    'Sessione cancellata',
                    'Il tuo coach ha cancellato la sessione del '.$booking->starts_at->format('d/m/Y H:i').'.',
                );
                $this->email->sendCancellation($booking, $reason);
            } elseif ($booking->guest_email) {
                $this->email->sendCancellation($booking, $reason);
            }
        } else {
            if ($booking->coach->user) {
                $this->push->send(
                    $booking->coach->user,
                    'Sessione cancellata',
                    "Il cliente ha cancellato la sessione del {$booking->starts_at->format('d/m/Y H:i')}.",
                );
            }
        }

        Log::info('BOOKING_CANCELLED', ['booking_id' => $booking->id, 'by' => $cancelledBy]);
    }

    public function complete(Booking $booking): void
    {
        $booking->update(['status' => BookingStatus::Completed->value]);

        Log::info('BOOKING_COMPLETED', ['booking_id' => $booking->id]);
    }

    public function markNoShow(Booking $booking): void
    {
        $booking->update(['status' => BookingStatus::NoShow->value]);

        Log::info('BOOKING_NO_SHOW', ['booking_id' => $booking->id]);
    }

    /**
     * @return Booking[]
     */
    public function createRecurring(Coach $coach, Client $client, array $data): array
    {
        $rule = $data['recurrence_rule'];
        $weeks = match ($rule) {
            'weekly' => 1,
            'biweekly' => 2,
            'monthly' => 4,
            default => 1,
        };

        $totalInstances = (int) (12 / ($weeks === 4 ? 4 : $weeks));
        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt = Carbon::parse($data['ends_at']);
        $duration = (int) $startsAt->diffInMinutes($endsAt);

        $parentBooking = $this->createForCoach($coach, $client, $data);
        $parentBooking->update(['recurrence_rule' => $rule]);

        $bookings = [$parentBooking];

        for ($i = 1; $i < $totalInstances; $i++) {
            $nextStart = $startsAt->copy()->addWeeks($weeks * $i);
            $nextEnd = $nextStart->copy()->addMinutes($duration);

            if (! $this->checkAvailability($coach, $nextStart, $nextEnd)) {
                continue;
            }

            $booking = Booking::create([
                'coach_id' => $coach->id,
                'client_id' => $client->id,
                'service_type' => $data['service_type'],
                'starts_at' => $nextStart,
                'ends_at' => $nextEnd,
                'duration_minutes' => $duration,
                'status' => BookingStatus::Confirmed->value,
                'source' => BookingSource::Manual->value,
                'notes' => $data['notes'] ?? null,
                'price' => $data['price'] ?? null,
                'recurrence_rule' => $rule,
                'recurrence_parent_id' => $parentBooking->id,
            ]);

            $this->dispatchReminderJobs($booking);
            $bookings[] = $booking;
        }

        Log::info('BOOKING_RECURRING_CREATED', [
            'parent_id' => $parentBooking->id,
            'count' => count($bookings),
            'rule' => $rule,
        ]);

        return $bookings;
    }

    public function reschedule(Booking $booking, array $data): void
    {
        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt = Carbon::parse($data['ends_at']);

        if (! $this->checkAvailability($booking->coach, $startsAt, $endsAt, $booking->id)) {
            throw new BookingOverlapException;
        }

        $booking->update([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'duration_minutes' => (int) $startsAt->diffInMinutes($endsAt),
        ]);

        $this->calendar->updateEvent($booking);

        if ($booking->client?->user) {
            $this->push->send(
                $booking->client->user,
                'Sessione spostata',
                "La sessione è stata spostata al {$booking->starts_at->format('d/m/Y H:i')}.",
            );
        }
    }

    private function dispatchReminderJobs(Booking $booking): void
    {
        $now = now();

        $delay24h = $booking->starts_at->copy()->subHours(24);
        if ($delay24h->gt($now)) {
            SendBookingReminderJob::dispatch($booking->id, '24h')->delay($delay24h);
        }

        $delay1h = $booking->starts_at->copy()->subHour();
        if ($delay1h->gt($now)) {
            SendBookingReminderJob::dispatch($booking->id, '1h')->delay($delay1h);
        }
    }

    private function generateCancellationToken(Booking $booking): string
    {
        return app(JwtService::class)->generateBookingCancellationToken($booking->id);
    }
}
