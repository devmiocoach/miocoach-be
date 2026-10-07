<?php

namespace Modules\WorkoutPlans\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutExerciseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exerciseId' => $this->exercise_id,
            'exercise' => $this->whenLoaded('exercise', fn () => new ExerciseResource($this->exercise)),
            'sets' => $this->sets,
            'repsTarget' => $this->reps_target,
            'weightKg' => $this->weight_kg,
            'rirTarget' => $this->rir_target,
            'restSeconds' => $this->rest_seconds,
            'notes' => $this->notes,
            'orderIndex' => $this->order_index,
        ];
    }
}
