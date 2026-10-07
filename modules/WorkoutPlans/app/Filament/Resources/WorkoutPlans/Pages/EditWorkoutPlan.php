<?php

namespace Modules\WorkoutPlans\Filament\Resources\WorkoutPlans\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\WorkoutPlans\Filament\Resources\WorkoutPlans\WorkoutPlanResource;

class EditWorkoutPlan extends EditRecord
{
    protected static string $resource = WorkoutPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RestoreAction::make(),
            DeleteAction::make()->requiresConfirmation(),
            ForceDeleteAction::make()->requiresConfirmation(),
        ];
    }
}
