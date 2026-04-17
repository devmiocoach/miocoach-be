<?php

namespace App\Modules\WorkoutPlans\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExerciseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'slug'          => $this->slug,
            'description'   => $this->description,
            'muscle_group'  => $this->muscle_group,
            'equipment'     => $this->equipment,
            'difficulty'    => $this->difficulty,
            'video_url'     => $this->video_url,
            'thumbnail_url' => $this->thumbnail_url,
            'tags'          => $this->tags,
            'is_public'     => $this->is_public,
            'pivot'         => $this->when($this->pivot, fn () => [
                'order'            => $this->pivot->order,
                'sets'             => $this->pivot->sets,
                'reps'             => $this->pivot->reps,
                'rest_seconds'     => $this->pivot->rest_seconds,
                'weight_kg'        => $this->pivot->weight_kg,
                'duration_seconds' => $this->pivot->duration_seconds,
                'notes'            => $this->pivot->notes,
                'day_of_week'      => $this->pivot->day_of_week,
            ]),
        ];
    }
}
