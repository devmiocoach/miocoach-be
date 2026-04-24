<?php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCoachProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->province) {
            $this->merge(['province' => mb_strtoupper($this->province)]);
        }
    }

    public function rules(): array
    {
        $coachId = $this->user()->coach?->id;

        return [
            // Profilo pubblico
            'display_name'               => ['sometimes', 'nullable', 'string', 'max:255'],
            'bio'                        => ['sometimes', 'nullable', 'string', 'max:500'],
            'tagline'                    => ['sometimes', 'nullable', 'string', 'max:150'],
            'description'                => ['sometimes', 'nullable', 'string', 'max:3000'],
            'website_url'                => ['sometimes', 'nullable', 'url', 'max:255'],
            'intro_video_url'            => ['sometimes', 'nullable', 'url', 'max:255'],
            'instagram_url'              => ['sometimes', 'nullable', 'url', 'max:255'],
            'specializations'            => ['sometimes', 'nullable', 'array'],
            'specializations.*'          => ['string', 'max:100'],
            'languages'                  => ['sometimes', 'nullable', 'array'],
            'languages.*'                => ['string', 'max:10'],
            'social_links'               => ['sometimes', 'nullable', 'array'],
            'social_links.instagram'     => ['sometimes', 'nullable', 'url', 'max:255'],
            'social_links.facebook'      => ['sometimes', 'nullable', 'url', 'max:255'],
            'social_links.youtube'       => ['sometimes', 'nullable', 'url', 'max:255'],
            'social_links.linkedin'      => ['sometimes', 'nullable', 'url', 'max:255'],
            'social_links.twitter'       => ['sometimes', 'nullable', 'url', 'max:255'],
            'years_of_experience'        => ['sometimes', 'nullable', 'integer', 'min:0', 'max:60'],
            'mode'                       => ['sometimes', 'nullable', 'in:online,in_person,hybrid'],

            // Contatti e sede
            'phone'    => ['sometimes', 'nullable', 'regex:/^[+]?[\d\s\-()]{7,20}$/'],
            'address'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'city'     => ['sometimes', 'nullable', 'string', 'max:100'],
            'province' => ['sometimes', 'nullable', 'string', 'size:2', 'alpha'],

            // Tariffe e capacità
            'hourly_rate'               => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999.99'],
            'price_per_session'         => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999.99'],
            'cancellation_window_hours' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:168'],
            'max_clients'               => ['sometimes', 'nullable', 'integer', 'min:1', 'max:500'],
            'is_visible'                => ['sometimes', 'boolean'],

            // Dati fiscali
            'ragione_sociale' => ['sometimes', 'nullable', 'string', 'max:255'],
            'p_iva'           => ['sometimes', 'nullable', 'regex:/^\d{11}$/', Rule::unique('coaches', 'p_iva')->ignore($coachId)],
            'codice_fiscale'  => ['sometimes', 'nullable', 'regex:/^[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z]$/i', Rule::unique('coaches', 'codice_fiscale')->ignore($coachId)],
            'tax_regime'      => ['sometimes', 'nullable', 'in:forfettario,ordinario,semplificato'],
            'sdi_code'        => ['sometimes', 'nullable', 'string', 'size:7', 'regex:/^[A-Z0-9]{7}$/i'],
            'pec'             => ['sometimes', 'nullable', 'email:rfc,dns'],
        ];
    }
}
