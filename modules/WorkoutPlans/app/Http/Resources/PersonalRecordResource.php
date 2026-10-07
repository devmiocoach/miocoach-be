<?php

namespace Modules\WorkoutPlans\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonalRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exerciseId' => $this->exercise_id,
            'exercise' => $this->whenLoaded('exercise', fn () => new ExerciseResource($this->exercise)),
            'weightKg' => $this->weight_kg,
            'recordedAt' => $this->recorded_at?->toISOString(),
        ];
    }
}
