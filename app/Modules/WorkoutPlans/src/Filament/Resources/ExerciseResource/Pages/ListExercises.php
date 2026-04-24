<?php

namespace App\Modules\WorkoutPlans\Filament\Resources\ExerciseResource\Pages;

use App\Modules\WorkoutPlans\Filament\Resources\ExerciseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExercises extends ListRecords
{
    protected static string $resource = ExerciseResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
