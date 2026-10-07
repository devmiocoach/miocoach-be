<?php

namespace Modules\WorkoutPlans\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutDayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dayNumber' => $this->day_number,
            'name' => $this->name,
            'orderIndex' => $this->order_index,
            'exercises' => WorkoutExerciseResource::collection($this->whenLoaded('workoutExercises')),
        ];
    }
}
