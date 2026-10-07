<?php

namespace Modules\Chat\Filament\Resources\Conversations\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ConversationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make()
                    ->inlineLabel()
                    ->schema([
                        TextEntry::make('coach.user.name')
                            ->label(__('Coach')),
                        TextEntry::make('client.user.name')
                            ->label(__('Client')),
                        TextEntry::make('last_message_at')
                            ->label(__('Last message'))
                            ->dateTime(),
                        TextEntry::make('created_at')
                            ->label(__('Started'))
                            ->dateTime(),
                    ])
                    ->columnSpan(2),
            ]);
    }
}
