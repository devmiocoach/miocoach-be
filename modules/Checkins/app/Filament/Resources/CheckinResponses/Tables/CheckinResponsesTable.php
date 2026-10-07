<?php

namespace Modules\Checkins\Filament\Resources\CheckinResponses\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CheckinResponsesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('checkinForm.title')
                    ->label(__('Form'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('client.user.name')
                    ->label(__('Client'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('submitted_at')
                    ->label(__('Submitted at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('submitted_at', 'desc');
    }
}
