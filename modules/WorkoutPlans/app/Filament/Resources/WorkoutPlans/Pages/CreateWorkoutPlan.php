<?php

namespace Modules\WorkoutPlans\Filament\Resources\WorkoutPlans\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\WorkoutPlans\Filament\Resources\WorkoutPlans\WorkoutPlanResource;

class CreateWorkoutPlan extends CreateRecord
{
    protected static string $resource = WorkoutPlanResource::class;
}
