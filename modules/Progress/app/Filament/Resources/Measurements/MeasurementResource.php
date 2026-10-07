<?php

namespace Modules\Progress\Filament\Resources\Measurements;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Progress\Filament\Resources\Measurements\Pages\CreateMeasurement;
use Modules\Progress\Filament\Resources\Measurements\Pages\EditMeasurement;
use Modules\Progress\Filament\Resources\Measurements\Pages\ListMeasurements;
use Modules\Progress\Filament\Resources\Measurements\Schemas\MeasurementForm;
use Modules\Progress\Filament\Resources\Measurements\Tables\MeasurementsTable;
use Modules\Progress\Models\BodyMeasurement;

class MeasurementResource extends Resource
{
    protected static ?string $model = BodyMeasurement::class;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('Progress');
    }

    public static function getNavigationLabel(): string
    {
        return __('Measurements');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['client.user.name', 'metric_type'];
    }

    public static function form(Schema $schema): Schema
    {
        return MeasurementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MeasurementsTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['client.user', 'coach.user']));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMeasurements::route('/'),
            'create' => CreateMeasurement::route('/create'),
            'edit' => EditMeasurement::route('/{record}/edit'),
        ];
    }
}
