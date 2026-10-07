<?php

namespace Modules\WorkoutPlans\Filament\Resources\WorkoutPlans\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\WorkoutPlans\Filament\Resources\WorkoutPlans\WorkoutPlanResource;

class ListWorkoutPlans extends ListRecords
{
    protected static string $resource = WorkoutPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
