<?php

namespace Modules\Progress\Filament\Resources\Measurements\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Progress\Filament\Resources\Measurements\MeasurementResource;

class EditMeasurement extends EditRecord
{
    protected static string $resource = MeasurementResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
