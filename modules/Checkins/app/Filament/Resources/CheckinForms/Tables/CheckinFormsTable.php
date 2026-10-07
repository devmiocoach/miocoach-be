<?php

namespace Modules\Checkins\Filament\Resources\CheckinForms\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Checkins\Enums\CheckinFrequency;

class CheckinFormsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('Title'))
                    ->searchable(),

                TextColumn::make('coach.user.name')
                    ->label(__('Coach'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('frequency')
                    ->label(__('Frequency'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('send_time')
                    ->label(__('Send time')),

                IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('clients_count')
                    ->label(__('Assigned'))
                    ->counts('clients')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('frequency')
                    ->label(__('Frequency'))
                    ->options(CheckinFrequency::class),

                TernaryFilter::make('is_active')
                    ->label(__('Active Only')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
