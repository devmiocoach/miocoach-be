<?php

namespace Modules\Checkins\Filament\Resources\CheckinForms\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Checkins\Filament\Resources\CheckinForms\CheckinFormResource;

class EditCheckinForm extends EditRecord
{
    protected static string $resource = CheckinFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->requiresConfirmation(),
        ];
    }
}
