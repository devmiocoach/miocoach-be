<?php

namespace Modules\Progress\Filament\Resources\ProgressPhotos;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Progress\Filament\Resources\ProgressPhotos\Pages\CreateProgressPhoto;
use Modules\Progress\Filament\Resources\ProgressPhotos\Pages\EditProgressPhoto;
use Modules\Progress\Filament\Resources\ProgressPhotos\Pages\ListProgressPhotos;
use Modules\Progress\Filament\Resources\ProgressPhotos\Schemas\ProgressPhotoForm;
use Modules\Progress\Filament\Resources\ProgressPhotos\Tables\ProgressPhotosTable;
use Modules\Progress\Models\ProgressPhoto;

class ProgressPhotoResource extends Resource
{
    protected static ?string $model = ProgressPhoto::class;

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('Progress');
    }

    public static function getNavigationLabel(): string
    {
        return __('Photos');
    }

    public static function form(Schema $schema): Schema
    {
        return ProgressPhotoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProgressPhotosTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['client.user', 'coach.user']));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProgressPhotos::route('/'),
            'create' => CreateProgressPhoto::route('/create'),
            'edit' => EditProgressPhoto::route('/{record}/edit'),
        ];
    }
}
