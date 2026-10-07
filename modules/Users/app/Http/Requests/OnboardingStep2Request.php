<?php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OnboardingStep2Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'specialization_ids' => ['nullable', 'array', 'max:10', 'required_without:custom_specializations'],
            'specialization_ids.*' => ['integer', 'distinct', 'exists:specializations,id'],
            'custom_specializations' => ['nullable', 'array', 'max:5', 'required_without:specialization_ids'],
            'custom_specializations.*' => ['string', 'max:60'],
            'languages' => ['required', 'array', 'min:1'],
            'languages.*' => ['string', 'max:10'],
            'mode' => ['required', 'in:online,in_person,hybrid'],
        ];
    }
}
