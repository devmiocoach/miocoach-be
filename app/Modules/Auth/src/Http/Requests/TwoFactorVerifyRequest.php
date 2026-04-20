<?php

namespace App\Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class TwoFactorVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $key = '2fa-login:' . $this->ip();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'code' => ["Troppi tentativi. Riprova tra {$seconds} secondi."],
            ]);
        }

        RateLimiter::hit($key, decay: 60 * 15);
    }

    public function rules(): array
    {
        return [
            'temp_token'  => ['required', 'string'],
            'code'        => ['required', 'string', 'max:16'], // TOTP (6 digits) or backup code
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
