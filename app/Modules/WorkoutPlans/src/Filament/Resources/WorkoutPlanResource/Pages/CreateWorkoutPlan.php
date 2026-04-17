<?php

namespace App\Modules\WorkoutPlans\Filament\Resources\WorkoutPlanResource\Pages;

use App\Modules\WorkoutPlans\Filament\Resources\WorkoutPlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkoutPlan extends CreateRecord
{
    protected static string $resource = WorkoutPlanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['coach_id'] = auth()->id();
        return $data;
    }
}
