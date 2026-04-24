<?php

namespace App\Modules\WorkoutPlans\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'title'             => $this->title,
            'slug'              => $this->slug,
            'description'       => $this->description,
            'goal'              => $this->goal,
            'duration_weeks'    => $this->duration_weeks,
            'sessions_per_week' => $this->sessions_per_week,
            'difficulty'        => $this->difficulty,
            'status'            => $this->status,
            'is_template'       => $this->is_template,
            'coach_id'          => $this->coach_id,
            'client_id'         => $this->client_id,
            'exercises'         => ExerciseResource::collection($this->whenLoaded('exercises')),
            'exercises_count'   => $this->whenCounted('exercises'),
            'created_at'        => $this->created_at?->toISOString(),
            'updated_at'        => $this->updated_at?->toISOString(),
        ];
    }
}
