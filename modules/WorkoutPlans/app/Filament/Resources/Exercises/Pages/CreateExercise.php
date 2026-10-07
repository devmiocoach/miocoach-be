<?php

namespace Modules\WorkoutPlans\Filament\Resources\Exercises\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\WorkoutPlans\Filament\Resources\Exercises\ExerciseResource;

class CreateExercise extends CreateRecord
{
    protected static string $resource = ExerciseResource::class;
}
