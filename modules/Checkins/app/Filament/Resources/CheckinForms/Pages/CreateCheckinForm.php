<?php

namespace Modules\Checkins\Filament\Resources\CheckinForms\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Checkins\Filament\Resources\CheckinForms\CheckinFormResource;

class CreateCheckinForm extends CreateRecord
{
    protected static string $resource = CheckinFormResource::class;
}
