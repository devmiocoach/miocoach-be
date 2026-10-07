<?php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCoachPackageRequest extends FormRequest
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
            'sessions_count' => ['sometimes', 'integer', 'min:1', 'max:9999'],
            'price_cents' => ['sometimes', 'integer', 'min:0', 'max:9999999'],
            'validity_days' => ['sometimes', 'integer', 'min:1', 'max:3650'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
