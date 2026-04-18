<?php

namespace App\Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterCoachRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Credenziali
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email:rfc,dns', 'max:255', 'lowercase', 'unique:users'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised(),
            ],

            // Profilo opzionale (completabile in seguito)
            'phone'               => ['sometimes', 'string', 'max:20'],
            'city'                => ['sometimes', 'string', 'max:100'],
            'province'            => ['sometimes', 'string', 'size:2'],
            'bio'                 => ['sometimes', 'string', 'max:500'],
            'specializations'     => ['sometimes', 'array'],
            'specializations.*'   => ['string', 'max:100'],
            'years_of_experience' => ['sometimes', 'integer', 'min:0', 'max:60'],
            'hourly_rate'         => ['sometimes', 'numeric', 'min:0', 'max:9999'],
            'p_iva'          => ['sometimes', 'string', 'regex:/^\d{11}$/'],
            'codice_fiscale' => ['sometimes', 'string', 'regex:/^[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z]$/i'],
            'tax_regime'          => ['sometimes', 'string', 'in:forfettario,ordinario,semplificato'],

            // Device
            'device_type' => ['sometimes', 'string', 'in:mobile,web'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->email) {
            $this->merge(['email' => mb_strtolower($this->email)]);
        }

        if ($this->province) {
            $this->merge(['province' => mb_strtoupper($this->province)]);
        }
    }
}
