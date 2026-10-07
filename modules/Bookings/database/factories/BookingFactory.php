<?php

namespace Modules\Bookings\Database\Factories;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Bookings\Enums\BookingServiceType;
use Modules\Bookings\Enums\BookingSource;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Models\Booking;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $startsAt = $this->faker->dateTimeBetween('+1 day', '+30 days');
        $duration = $this->faker->randomElement([30, 45, 60, 90]);
        $endsAt = (clone $startsAt)->modify("+{$duration} minutes");

        return [
            'coach_id' => Coach::factory(),
            'client_id' => Client::factory(),
            'service_type' => $this->faker->randomElement(BookingServiceType::cases())->value,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'duration_minutes' => $duration,
            'status' => BookingStatus::Confirmed->value,
            'source' => BookingSource::Manual->value,
            'notes' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(['status' => BookingStatus::Confirmed->value]);
    }

    public function completed(): static
    {
        return $this->state(['status' => BookingStatus::Completed->value]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => BookingStatus::Cancelled->value,
            'cancelled_at' => now(),
            'cancelled_by' => 'coach',
            'cancellation_reason' => $this->faker->sentence(),
        ]);
    }

    public function forGuest(): static
    {
        return $this->state([
            'client_id' => null,
            'guest_name' => $this->faker->name(),
            'guest_email' => $this->faker->email(),
            'guest_phone' => $this->faker->phoneNumber(),
            'source' => BookingSource::PublicWeb->value,
        ]);
    }
}
