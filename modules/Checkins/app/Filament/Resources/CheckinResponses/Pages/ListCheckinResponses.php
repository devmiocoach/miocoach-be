<?php

namespace Modules\Checkins\Filament\Resources\CheckinResponses\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Checkins\Filament\Resources\CheckinResponses\CheckinResponseResource;

class ListCheckinResponses extends ListRecords
{
    protected static string $resource = CheckinResponseResource::class;
}
