<?php

namespace App\Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'       => ['required', 'string', 'email', 'max:255'],
            'password'    => ['required', 'string', 'max:1024'],
            'device_type' => ['sometimes', 'string', 'in:mobile,web'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
