<?php

namespace Modules\WorkoutPlans\Filament\Resources\WorkoutSessions\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\WorkoutPlans\Filament\Resources\WorkoutSessions\WorkoutSessionResource;

class ListWorkoutSessions extends ListRecords
{
    protected static string $resource = WorkoutSessionResource::class;
}
