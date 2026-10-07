<?php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OnboardingStep5Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'city' => ['nullable', 'string', 'max:100'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'service_radius_km' => ['required', 'integer', 'min:1', 'max:50'],
            'available_at_home' => ['required', 'boolean'],
            'partner_gyms' => ['nullable', 'array', 'max:20'],
            'partner_gyms.*' => ['string', 'max:150'],
        ];
    }
}
