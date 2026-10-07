<?php

namespace Modules\Progress\Filament\Resources\ProgressPhotos\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Progress\Filament\Resources\ProgressPhotos\ProgressPhotoResource;

class EditProgressPhoto extends EditRecord
{
    protected static string $resource = ProgressPhotoResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
