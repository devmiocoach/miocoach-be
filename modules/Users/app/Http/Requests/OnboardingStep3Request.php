<?php

namespace App\Modules\Users\Http\Requests;

use App\Modules\Users\Rules\ValidPIva;
use Illuminate\Foundation\Http\FormRequest;

class OnboardingStep3Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'certifications' => ['nullable', 'array', 'max:20'],
            'certifications.*.name' => ['required', 'string', 'max:150'],
            'certifications.*.issuer' => ['nullable', 'string', 'max:100'],
            'certifications.*.year' => ['nullable', 'string', 'regex:/^\d{4}$/'],
            'p_iva' => ['nullable', 'string', 'regex:/^\d{11}$/', new ValidPIva],
            'has_insurance' => ['required', 'boolean'],
            'insurance_company' => ['nullable', 'required_if:has_insurance,true', 'string', 'max:100'],
            'insurance_expiry' => ['nullable', 'required_if:has_insurance,true', 'date'],
        ];
    }
}
