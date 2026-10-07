<?php

namespace Modules\Checkins\Filament\Resources\CheckinForms;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Checkins\Filament\Resources\CheckinForms\Pages\CreateCheckinForm;
use Modules\Checkins\Filament\Resources\CheckinForms\Pages\EditCheckinForm;
use Modules\Checkins\Filament\Resources\CheckinForms\Pages\ListCheckinForms;
use Modules\Checkins\Filament\Resources\CheckinForms\Schemas\CheckinFormForm;
use Modules\Checkins\Filament\Resources\CheckinForms\Tables\CheckinFormsTable;
use Modules\Checkins\Models\CheckinForm;
use UnitEnum;

class CheckinFormResource extends Resource
{
    protected static ?string $model = CheckinForm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Check-ins';

    public static function getGloballySearchableAttributes(): array
    {
        return ['title'];
    }

    public static function form(Schema $schema): Schema
    {
        return CheckinFormForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CheckinFormsTable::configure($table);
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListCheckinForms::route('/'),
            'create' => CreateCheckinForm::route('/create'),
            'edit' => EditCheckinForm::route('/{record}/edit'),
        ];
    }
}
