<?php

namespace App\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoachResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isOwner = $request->user()?->id === $this->user_id;

        return [
            // Campi pubblici
            'id'                        => $this->id,
            'slug'                      => $this->slug,
            'bio'                       => $this->bio,
            'tagline'                   => $this->tagline,
            'description'               => $this->description,
            'website_url'               => $this->website_url,
            'intro_video_url'           => $this->intro_video_url,
            'specializations'           => $this->specializations,
            'languages'                 => $this->languages,
            'certifications'            => $this->certifications,
            'social_links'              => $this->social_links,
            'years_of_experience'       => $this->years_of_experience,
            'hourly_rate'               => $this->hourly_rate,
            'price_per_session'         => $this->price_per_session,
            'cancellation_window_hours' => $this->cancellation_window_hours,
            'city'                      => $this->city,
            'mode'                      => $this->mode,
            'lat'                       => $this->lat,
            'lng'                       => $this->lng,
            'is_verified'               => $this->is_verified,
            'is_visible'                => $this->is_visible,
            'is_published'              => $this->is_published,
            'user'                      => $this->whenLoaded('user', fn () => new UserResource($this->user)),

            // Campi visibili solo al proprietario
            'phone'                       => $this->when($isOwner, $this->phone),
            'address'                     => $this->when($isOwner, $this->address),
            'province'                    => $this->when($isOwner, $this->province),
            'max_clients'                 => $this->when($isOwner, $this->max_clients),
            'ragione_sociale'             => $this->when($isOwner, $this->ragione_sociale),
            'p_iva'                       => $this->when($isOwner, $this->p_iva),
            'codice_fiscale'              => $this->when($isOwner, $this->codice_fiscale),
            'tax_regime'                  => $this->when($isOwner, $this->tax_regime),
            'sdi_code'                    => $this->when($isOwner, $this->sdi_code),
            'pec'                         => $this->when($isOwner, $this->pec),
            'stripe_connect_id'           => $this->when($isOwner, $this->stripe_connect_id),
            'stripe_onboarding_completed' => $this->when($isOwner, $this->stripe_onboarding_completed),
        ];
    }
}
