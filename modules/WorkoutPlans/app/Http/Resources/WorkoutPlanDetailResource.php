<?php

namespace Modules\WorkoutPlans\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutPlanDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'goal' => $this->goal?->value,
            'difficulty' => $this->difficulty?->value,
            'durationWeeks' => $this->duration_weeks,
            'sessionsPerWeek' => $this->sessions_per_week,
            'isTemplate' => $this->is_template,
            'isActive' => $this->is_active,
            'coachId' => $this->coach_id,
            'days' => WorkoutDayResource::collection($this->whenLoaded('workoutDays')),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
