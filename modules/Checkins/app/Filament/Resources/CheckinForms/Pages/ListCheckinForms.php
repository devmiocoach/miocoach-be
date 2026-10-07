<?php

namespace Modules\Checkins\Filament\Resources\CheckinForms\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Checkins\Filament\Resources\CheckinForms\CheckinFormResource;

class ListCheckinForms extends ListRecords
{
    protected static string $resource = CheckinFormResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
