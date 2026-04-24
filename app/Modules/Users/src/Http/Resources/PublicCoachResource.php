<?php

namespace App\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicCoachResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $displayName = $this->user?->name ?? $this->slug;
        $city        = $this->city ?? '';

        return [
            'id'                        => $this->id,
            'slug'                      => $this->slug,
            'display_name'              => $displayName,
            'bio'                       => $this->bio,
            'tagline'                   => $this->tagline,
            'specializations'           => $this->specializations,
            'languages'                 => $this->languages,
            'mode'                      => $this->mode,
            'city'                      => $city,
            'hourly_rate'               => $this->hourly_rate,
            'price_per_session'         => $this->price_per_session,
            'cancellation_window_hours' => $this->cancellation_window_hours,
            'years_of_experience'       => $this->years_of_experience,
            'is_verified'               => $this->is_verified,

            // Rating — populated by Reviews module when available
            'rating'                    => null,
            'reviews_count'             => 0,

            // Verified certifications only
            'certifications' => CertificationResource::collection(
                $this->whenLoaded('verifiedCertifications')
            ),

            // Next 5 availability slots
            'next_slots' => CoachAvailabilityResource::collection(
                $this->whenLoaded('nextSlots')
            ),

            // SEO metadata
            'seo' => [
                'title'       => $displayName . ' — Coach ' . ($this->tagline ?? 'Personal Trainer'),
                'description' => mb_substr($this->bio ?? '', 0, 160),
                'og_image'    => null,
            ],
        ];
    }
}
