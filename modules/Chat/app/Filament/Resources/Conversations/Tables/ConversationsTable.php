<?php

namespace Modules\Chat\Filament\Resources\Conversations\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConversationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('coach.user.name')
                    ->label(__('Coach'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client.user.name')
                    ->label(__('Client'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('messages_count')
                    ->label(__('Messages'))
                    ->counts('messages')
                    ->sortable(),
                TextColumn::make('last_message_at')
                    ->label(__('Last message'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('last_message_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
