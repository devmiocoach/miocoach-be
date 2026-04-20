<?php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCertificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file'      => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'name'      => ['required', 'string', 'max:255'],
            'issuer'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'issued_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
