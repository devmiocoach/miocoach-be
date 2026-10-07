<?php

namespace Modules\Chat\Filament\Resources\Conversations\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Chat\Enums\MessageType;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $title = 'Messages';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sender.name')
                    ->label(__('Sender')),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof MessageType ? $state->value : $state),
                TextColumn::make('body')
                    ->label(__('Body'))
                    ->limit(80)
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label(__('Sent'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('id', 'asc')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
