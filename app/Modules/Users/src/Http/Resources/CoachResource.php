<?php

namespace App\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoachResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'slug'           => $this->slug,
            'bio'            => $this->bio,
            'description'    => $this->description,
            'specialization' => $this->specialization,
            'certifications' => $this->certifications,
            'hourly_rate'    => $this->hourly_rate,
            'city'           => $this->city,
            'is_verified'    => $this->is_verified,
            'is_visible'     => $this->is_visible,
            'social_links'   => $this->social_links,
            'user'           => $this->whenLoaded('user', fn() => new UserResource($this->user)),
        ];
    }
}
