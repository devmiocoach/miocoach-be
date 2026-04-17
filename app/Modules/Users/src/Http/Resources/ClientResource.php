<?php

namespace App\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'status'     => $this->status,
            'goals'      => $this->goals,
            'birth_date' => $this->birth_date?->toDateString(),
            'gender'     => $this->gender,
            'height_cm'  => $this->height_cm,
            'weight_kg'  => $this->weight_kg,
            'joined_at'  => $this->joined_at?->toISOString(),
            'user'       => $this->whenLoaded('user', fn() => new UserResource($this->user)),
            'coach'      => $this->whenLoaded('coach', fn() => new CoachResource($this->coach)),
        ];
    }
}
