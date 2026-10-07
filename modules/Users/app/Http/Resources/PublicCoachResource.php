<?php

namespace App\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicCoachResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $displayName = $this->display_name ?? $this->user?->name ?? $this->slug;
        $city = $this->city ?? '';

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'display_name' => $displayName,
            'bio' => $this->bio,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'specializations' => $this->specializations,
            'languages' => $this->languages,
            'mode' => $this->mode,
            'gender' => $this->gender,
            'city' => $city,
            'province' => $this->province,
            'lat' => $this->lat ? round((float) $this->lat, 3) : null,
            'lng' => $this->lng ? round((float) $this->lng, 3) : null,
            'hourly_rate' => $this->hourly_rate,
            'price_per_session' => $this->price_per_session,
            'single_session_price_cents' => $this->single_session_price_cents,
            'free_first_session' => $this->free_first_session,
            'contact_options' => $this->resource->contactOptions(),
            'cancellation_window_hours' => $this->cancellation_window_hours,
            'session_duration_default' => $this->session_duration_default,
            'years_of_experience' => $this->years_of_experience,
            'is_verified' => $this->is_verified,
            'profile_photo_url' => $this->profile_photo_url,
            'cover_photo_url' => $this->cover_photo_url,
            'gallery_photos' => $this->gallery_photos,
            'intro_video_url' => $this->intro_video_url,
            'social_links' => $this->social_links,
            'website_url' => $this->website_url,

            'rating' => $this->avg_rating ? round((float) $this->avg_rating, 1) : null,
            'reviews_count' => (int) ($this->reviews_count ?? 0),

            'certifications' => CertificationResource::collection(
                $this->whenLoaded('verifiedCertifications')
            ),

            'packages' => CoachPackageResource::collection(
                $this->whenLoaded('activePackages')
            ),

            'availability' => CoachAvailabilityResource::collection(
                $this->whenLoaded('availabilities')
            ),

            'seo' => [
                'title' => $displayName.' — Personal Trainer '.($city ?: 'Online').' | MioCoach',
                'description' => ($this->tagline ? $this->tagline.'. ' : '').mb_substr($this->bio ?? '', 0, 140),
                'og_image' => $this->profile_photo_url,
            ],
        ];
    }
}
