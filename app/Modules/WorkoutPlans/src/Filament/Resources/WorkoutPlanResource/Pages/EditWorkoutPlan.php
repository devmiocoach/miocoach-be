<?php

namespace App\Modules\WorkoutPlans\Filament\Resources\WorkoutPlanResource\Pages;

use App\Modules\WorkoutPlans\Filament\Resources\WorkoutPlanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkoutPlan extends EditRecord
{
    protected static string $resource = WorkoutPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
