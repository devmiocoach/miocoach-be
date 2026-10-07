<?php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OnboardingStep1Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'min:2', 'max:100'],
            'tagline' => ['required', 'string', 'max:120'],
            'bio' => ['required', 'string', 'min:10', 'max:500'],
            'gender' => ['nullable', 'in:male,female'],
        ];
    }
}
