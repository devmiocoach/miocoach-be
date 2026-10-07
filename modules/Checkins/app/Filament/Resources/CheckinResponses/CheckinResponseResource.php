<?php

namespace Modules\Checkins\Filament\Resources\CheckinResponses;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Checkins\Filament\Resources\CheckinResponses\Pages\ListCheckinResponses;
use Modules\Checkins\Filament\Resources\CheckinResponses\Pages\ViewCheckinResponse;
use Modules\Checkins\Filament\Resources\CheckinResponses\Schemas\CheckinResponseInfolist;
use Modules\Checkins\Filament\Resources\CheckinResponses\Tables\CheckinResponsesTable;
use Modules\Checkins\Models\CheckinResponse;
use UnitEnum;

class CheckinResponseResource extends Resource
{
    protected static ?string $model = CheckinResponse::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    protected static string|UnitEnum|null $navigationGroup = 'Check-ins';

    public static function infolist(Schema $schema): Schema
    {
        return CheckinResponseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CheckinResponsesTable::configure($table);
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListCheckinResponses::route('/'),
            'view' => ViewCheckinResponse::route('/{record}'),
        ];
    }
}
