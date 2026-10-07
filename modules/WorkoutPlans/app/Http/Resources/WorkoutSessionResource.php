<?php

namespace Modules\WorkoutPlans\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clientId' => $this->client_id,
            'workoutPlanId' => $this->workout_plan_id,
            'workoutDayId' => $this->workout_day_id,
            'startedAt' => $this->started_at?->toISOString(),
            'completedAt' => $this->completed_at?->toISOString(),
            'totalVolumeKg' => $this->total_volume_kg,
            'durationMinutes' => $this->duration_minutes,
            'notes' => $this->notes,
            'day' => $this->whenLoaded('workoutDay', fn () => new WorkoutDayResource($this->workoutDay)),
        ];
    }
}
