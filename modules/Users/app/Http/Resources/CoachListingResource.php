<?php

namespace App\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoachListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'display_name' => $this->display_name ?? $this->user?->name ?? $this->slug,
            'tagline' => $this->tagline,
            'bio_excerpt' => mb_substr($this->bio ?? '', 0, 160),
            'city' => $this->city,
            'province' => $this->province,
            'mode' => $this->mode,
            'gender' => $this->gender,
            'specializations' => $this->specializationRecords
                ->map(fn ($s) => ['name' => $s->name, 'slug' => $s->slug])
                ->values(),
            'languages' => $this->languages,
            'price_from_cents' => $this->single_session_price_cents,
            'years_of_experience' => $this->years_of_experience,
            'is_verified' => $this->is_verified,
            'profile_photo_url' => $this->profile_photo_url,
            'lat' => $this->lat ? round((float) $this->lat, 2) : null,
            'lng' => $this->lng ? round((float) $this->lng, 2) : null,
            'rating' => $this->avg_rating ? round((float) $this->avg_rating, 1) : null,
            'reviews_count' => (int) ($this->reviews_count ?? 0),
        ];
    }
}
