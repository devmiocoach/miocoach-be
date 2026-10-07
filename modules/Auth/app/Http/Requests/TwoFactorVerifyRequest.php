<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'temp_token' => ['required', 'string', 'max:2048'],
            'code' => ['required', 'string', 'max:20'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
