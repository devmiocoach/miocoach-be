<?php

namespace Modules\WorkoutPlans\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExerciseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'category' => $this->category?->value,
            'difficulty' => $this->difficulty?->value,
            'primaryMuscles' => $this->primary_muscles ?? [],
            'secondaryMuscles' => $this->secondary_muscles ?? [],
            'muscleGroups' => $this->muscle_groups ?? [],
            'equipment' => $this->equipment ?? [],
            'videoUrl' => $this->video_url,
            'thumbnailUrl' => $this->thumbnail_url,
            'isCustom' => $this->coach_id !== null,
            'coachId' => $this->coach_id,
            'isActive' => $this->is_active,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
