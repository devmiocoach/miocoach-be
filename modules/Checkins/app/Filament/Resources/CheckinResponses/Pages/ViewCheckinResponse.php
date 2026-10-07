<?php

namespace Modules\Checkins\Filament\Resources\CheckinResponses\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\Checkins\Filament\Resources\CheckinResponses\CheckinResponseResource;

class ViewCheckinResponse extends ViewRecord
{
    protected static string $resource = CheckinResponseResource::class;
}
