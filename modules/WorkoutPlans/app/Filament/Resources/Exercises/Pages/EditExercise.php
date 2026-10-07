<?php

namespace Modules\WorkoutPlans\Filament\Resources\Exercises\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\WorkoutPlans\Filament\Resources\Exercises\ExerciseResource;

class EditExercise extends EditRecord
{
    protected static string $resource = ExerciseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RestoreAction::make(),
            DeleteAction::make()->requiresConfirmation(),
            ForceDeleteAction::make()->requiresConfirmation(),
        ];
    }
}
