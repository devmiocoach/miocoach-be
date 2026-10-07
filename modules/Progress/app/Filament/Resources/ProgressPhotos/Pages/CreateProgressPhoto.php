<?php

namespace Modules\Progress\Filament\Resources\ProgressPhotos\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Progress\Filament\Resources\ProgressPhotos\ProgressPhotoResource;

class CreateProgressPhoto extends CreateRecord
{
    protected static string $resource = ProgressPhotoResource::class;
}
