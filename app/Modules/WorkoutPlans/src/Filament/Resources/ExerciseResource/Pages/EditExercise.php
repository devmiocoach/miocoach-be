<?php

namespace App\Modules\WorkoutPlans\Filament\Resources\ExerciseResource\Pages;

use App\Modules\WorkoutPlans\Filament\Resources\ExerciseResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExercise extends EditRecord
{
    protected static string $resource = ExerciseResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
