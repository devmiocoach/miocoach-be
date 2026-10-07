<?php

namespace Modules\WorkoutPlans\Filament\Resources\Exercises\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\WorkoutPlans\Filament\Resources\Exercises\ExerciseResource;

class ListExercises extends ListRecords
{
    protected static string $resource = ExerciseResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
