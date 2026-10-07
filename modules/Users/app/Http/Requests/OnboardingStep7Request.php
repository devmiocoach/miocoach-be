<?php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OnboardingStep7Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'gallery_photos' => ['nullable', 'array', 'max:6'],
            'gallery_photos.*' => ['string', 'url', 'max:500'],
            'has_video' => ['nullable', 'boolean'],
        ];
    }
}
