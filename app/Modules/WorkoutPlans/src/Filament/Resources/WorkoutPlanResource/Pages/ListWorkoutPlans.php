<?php

namespace App\Modules\WorkoutPlans\Filament\Resources\WorkoutPlanResource\Pages;

use App\Modules\WorkoutPlans\Filament\Resources\WorkoutPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkoutPlans extends ListRecords
{
    protected static string $resource = WorkoutPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
