<?php

namespace Modules\Chat\Filament\Resources\Conversations;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Chat\Filament\Resources\Conversations\Pages\ListConversations;
use Modules\Chat\Filament\Resources\Conversations\Pages\ViewConversation;
use Modules\Chat\Filament\Resources\Conversations\RelationManagers\MessagesRelationManager;
use Modules\Chat\Filament\Resources\Conversations\Schemas\ConversationInfolist;
use Modules\Chat\Filament\Resources\Conversations\Tables\ConversationsTable;
use Modules\Chat\Models\Conversation;

class ConversationResource extends Resource
{
    protected static ?string $model = Conversation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('Chat');
    }

    public static function getNavigationLabel(): string
    {
        return __('Conversations');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return ConversationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConversationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MessagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConversations::route('/'),
            'view' => ViewConversation::route('/{record}'),
        ];
    }
}
