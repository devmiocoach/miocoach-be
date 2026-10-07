<?php

namespace Modules\Bookings\Filament\Resources\Bookings;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Bookings\Filament\Resources\Bookings\Pages\CreateBooking;
use Modules\Bookings\Filament\Resources\Bookings\Pages\EditBooking;
use Modules\Bookings\Filament\Resources\Bookings\Pages\ListBookings;
use Modules\Bookings\Filament\Resources\Bookings\Schemas\BookingForm;
use Modules\Bookings\Filament\Resources\Bookings\Tables\BookingsTable;
use Modules\Bookings\Models\Booking;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('Bookings');
    }

    public static function getNavigationLabel(): string
    {
        return __('Bookings');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['guest_name', 'guest_email'];
    }

    public static function form(Schema $schema): Schema
    {
        return BookingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BookingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookings::route('/'),
            'create' => CreateBooking::route('/create'),
            'edit' => EditBooking::route('/{record}/edit'),
        ];
    }
}
