<?php

namespace Modules\CoachCommerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'price_monthly' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999.99'],
            'price_yearly' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999.99'],
            'sessions_per_month' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:9999'],
            'features' => ['sometimes', 'nullable', 'array'],
            'features.*' => ['string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
