<?php

namespace Modules\Progress\Filament\Resources\Measurements\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Progress\Filament\Resources\Measurements\MeasurementResource;

class ListMeasurements extends ListRecords
{
    protected static string $resource = MeasurementResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
