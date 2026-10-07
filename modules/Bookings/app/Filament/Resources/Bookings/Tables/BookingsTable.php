<?php

namespace Modules\Bookings\Filament\Resources\Bookings\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Bookings\Enums\BookingCancelledBy;
use Modules\Bookings\Enums\BookingServiceType;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Services\BookingService;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('client.user.name')
                    ->label(__('Client'))
                    ->default(fn ($record) => $record->guest_name ?? '—')
                    ->searchable(query: fn ($query, string $search) => $query
                        ->whereHas('client.user', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                        ->orWhere('guest_name', 'like', "%{$search}%")),

                TextColumn::make('coach.user.name')
                    ->label(__('Coach'))
                    ->searchable(),

                TextColumn::make('service_type')
                    ->label(__('Service'))
                    ->badge(),

                TextColumn::make('starts_at')
                    ->label(__('Start'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('ends_at')
                    ->label(__('End'))
                    ->dateTime('d/m/Y H:i'),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),

                TextColumn::make('source')
                    ->label(__('Source'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(BookingStatus::class),

                SelectFilter::make('service_type')
                    ->label(__('Service Type'))
                    ->options(BookingServiceType::class),
            ])
            ->actions([
                EditAction::make()->icon(Heroicon::PencilSquare),
                DeleteAction::make()
                    ->action(fn ($record) => app(BookingService::class)->cancel(
                        $record,
                        'Cancelled from Filament.',
                        BookingCancelledBy::Coach->value,
                    )),
            ])
            ->defaultSort('starts_at', 'desc');
    }
}
