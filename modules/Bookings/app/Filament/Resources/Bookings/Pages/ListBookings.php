<?php

namespace Modules\Bookings\Filament\Resources\Bookings\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Bookings\Filament\Resources\Bookings\BookingResource;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
