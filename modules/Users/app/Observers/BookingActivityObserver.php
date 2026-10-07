<?php

namespace App\Modules\Users\Observers;

use App\Modules\Users\Enums\CoachActivityType;
use App\Modules\Users\Models\CoachActivity;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Models\Booking;

class BookingActivityObserver
{
    public function created(Booking $booking): void
    {
        CoachActivity::create([
            'coach_id' => $booking->coach_id,
            'event_type' => CoachActivityType::NewBooking,
            'subject_type' => Booking::class,
            'subject_id' => $booking->id,
            'metadata' => [
                'client_name' => $booking->client?->user->name ?? $booking->guest_name,
                'service_type' => $booking->service_type?->value,
                'starts_at' => $booking->starts_at?->toISOString(),
            ],
            'created_at' => now(),
        ]);
    }

    public function updated(Booking $booking): void
    {
        if (! $booking->isDirty('status')) {
            return;
        }

        if ($booking->status !== BookingStatus::Completed) {
            return;
        }

        CoachActivity::create([
            'coach_id' => $booking->coach_id,
            'event_type' => CoachActivityType::SessionCompleted,
            'subject_type' => Booking::class,
            'subject_id' => $booking->id,
            'metadata' => [
                'client_name' => $booking->client?->user->name ?? $booking->guest_name,
                'service_type' => $booking->service_type?->value,
            ],
            'created_at' => now(),
        ]);

        if ($booking->price > 0) {
            CoachActivity::create([
                'coach_id' => $booking->coach_id,
                'event_type' => CoachActivityType::PaymentReceived,
                'subject_type' => Booking::class,
                'subject_id' => $booking->id,
                'metadata' => [
                    'client_name' => $booking->client?->user->name ?? $booking->guest_name,
                    'amount' => $booking->price,
                ],
                'created_at' => now(),
            ]);
        }
    }
}
