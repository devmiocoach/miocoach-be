<?php

namespace App\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoachListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'slug'                => $this->slug,
            'display_name'        => $this->user?->name ?? $this->slug,
            'tagline'             => $this->tagline,
            'bio_excerpt'         => mb_substr($this->bio ?? '', 0, 120),
            'city'                => $this->city,
            'mode'                => $this->mode,
            'specializations'     => $this->specializations,
            'languages'           => $this->languages,
            'price_per_session'   => $this->price_per_session,
            'hourly_rate'         => $this->hourly_rate,
            'years_of_experience' => $this->years_of_experience,
            'is_verified'         => $this->is_verified,
            'rating'              => null,
            'reviews_count'       => 0,
        ];
    }
}
