<?php

namespace Modules\Bookings\Filament\Resources\Bookings\Schemas;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Bookings\Enums\BookingServiceType;
use Modules\Bookings\Enums\BookingStatus;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                Section::make(__('Booking Details'))->schema([
                    Select::make('coach_id')
                        ->label(__('Coach'))
                        ->options(fn () => Coach::query()
                            ->join('users', 'coaches.user_id', '=', 'users.id')
                            ->pluck('users.name', 'coaches.id')
                            ->toArray())
                        ->searchable()
                        ->required(),

                    Select::make('client_id')
                        ->label(__('Client'))
                        ->options(fn () => Client::query()
                            ->join('users', 'clients.user_id', '=', 'users.id')
                            ->pluck('users.name', 'clients.id')
                            ->toArray())
                        ->searchable()
                        ->nullable(),

                    Select::make('service_type')
                        ->label(__('Service Type'))
                        ->options(BookingServiceType::class)
                        ->required(),

                    DateTimePicker::make('starts_at')
                        ->label(__('Start'))
                        ->required(),

                    DateTimePicker::make('ends_at')
                        ->label(__('End'))
                        ->required(),

                    TextInput::make('price')
                        ->label(__('Price (€)'))
                        ->numeric()
                        ->nullable(),

                    Textarea::make('notes')
                        ->label(__('Notes'))
                        ->rows(3)
                        ->nullable(),
                ])->columnSpan(1),

                Section::make(__('Status & Guest'))->schema([
                    Select::make('status')
                        ->label(__('Status'))
                        ->options(BookingStatus::class)
                        ->required()
                        ->default(BookingStatus::Confirmed->value),

                    TextInput::make('guest_name')
                        ->label(__('Guest Name'))
                        ->nullable(),

                    TextInput::make('guest_email')
                        ->label(__('Guest Email'))
                        ->email()
                        ->nullable(),

                    TextInput::make('guest_phone')
                        ->label(__('Guest Phone'))
                        ->nullable(),
                ])->columnSpan(1),
            ]),
        ]);
    }
}
