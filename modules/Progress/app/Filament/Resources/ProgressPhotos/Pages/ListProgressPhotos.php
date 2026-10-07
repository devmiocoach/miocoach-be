<?php

namespace Modules\Progress\Filament\Resources\ProgressPhotos\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Progress\Filament\Resources\ProgressPhotos\ProgressPhotoResource;

class ListProgressPhotos extends ListRecords
{
    protected static string $resource = ProgressPhotoResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
