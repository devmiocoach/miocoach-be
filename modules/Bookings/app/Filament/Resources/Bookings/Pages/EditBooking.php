<?php

namespace Modules\Bookings\Filament\Resources\Bookings\Pages;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Bookings\Enums\BookingCancelledBy;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Filament\Resources\Bookings\BookingResource;
use Modules\Bookings\Services\BookingService;

class EditBooking extends EditRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->action(fn ($record) => app(BookingService::class)->cancel(
                    $record,
                    'Cancelled from Filament.',
                    BookingCancelledBy::Coach->value,
                )),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $data = $this->normalizeBookingData($data);
        $service = app(BookingService::class);

        if ($this->hasScheduleChanged($record, $data)) {
            $service->reschedule($record, $data);
            $record->refresh();
        }

        if (($data['status'] ?? null) === BookingStatus::Completed->value) {
            $service->complete($record);
            $record->refresh();
        } elseif (($data['status'] ?? null) === BookingStatus::Cancelled->value && $record->status !== BookingStatus::Cancelled) {
            $service->cancel($record, 'Cancelled from Filament.', BookingCancelledBy::Coach->value);
            $record->refresh();
        } elseif (isset($data['status'])) {
            $record->update(['status' => $data['status']]);
        }

        $record->fill(collect($data)
            ->only(['notes', 'price', 'guest_name', 'guest_email', 'guest_phone', 'service_type'])
            ->all());
        $record->save();

        return $record;
    }

    private function hasScheduleChanged(Model $record, array $data): bool
    {
        return isset($data['starts_at'], $data['ends_at'])
            && (
                ! $record->starts_at?->equalTo($data['starts_at'])
                || ! $record->ends_at?->equalTo($data['ends_at'])
            );
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
}
