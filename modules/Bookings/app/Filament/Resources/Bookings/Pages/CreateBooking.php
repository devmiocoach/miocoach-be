<?php

namespace Modules\Bookings\Filament\Resources\Bookings\Pages;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use BackedEnum;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Bookings\Filament\Resources\Bookings\BookingResource;
use Modules\Bookings\Services\BookingService;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $data = $this->normalizeBookingData($data);
        $coach = Coach::findOrFail($data['coach_id']);

        if (isset($data['client_id'])) {
            $client = Client::findOrFail($data['client_id']);

            return app(BookingService::class)->createForCoach($coach, $client, $data);
        }

        return app(BookingService::class)->createPublic($this->toPublicBookingData($coach, $data));
    }

    private function normalizeBookingData(array $data): array
    {
        foreach (['service_type', 'status'] as $key) {
            if (($data[$key] ?? null) instanceof BackedEnum) {
                $data[$key] = $data[$key]->value;
            }
        }

        return $data;
    }

    private function toPublicBookingData(Coach $coach, array $data): array
    {
        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt = Carbon::parse($data['ends_at']);

        return [
            'coach_id' => $coach->id,
            'slot_starts_at' => $startsAt->toDateTimeString(),
            'duration_minutes' => (int) $startsAt->diffInMinutes($endsAt),
            'service_type' => $data['service_type'],
            'package_id' => $data['package_id'] ?? null,
            'guest_name' => $data['guest_name'],
            'guest_email' => $data['guest_email'],
            'guest_phone' => $data['guest_phone'] ?? null,
        ];
    }
}
