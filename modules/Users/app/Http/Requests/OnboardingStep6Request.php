<?php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OnboardingStep6Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'single_session_price_cents' => ['required', 'integer', 'min:100'],
            'packages' => ['nullable', 'array', 'max:10'],
            'packages.*.name' => ['required', 'string', 'max:100'],
            'packages.*.sessions_count' => ['required', 'integer', 'min:2', 'max:100'],
            'packages.*.price_cents' => ['required', 'integer', 'min:100'],
            'monthly_subscription_cents' => ['nullable', 'integer', 'min:100'],
            'quarterly_subscription_cents' => ['nullable', 'integer', 'min:100'],
        ];
    }
}
