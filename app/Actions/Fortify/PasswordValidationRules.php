<?php

namespace App\Actions\Fortify;

use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    protected function passwordRules(): array
    {
        return [
            'required',
            'string',
            Password::min(8)
                ->mixedCase()   // almeno 1 maiuscola + 1 minuscola
                ->numbers()     // almeno 1 cifra
                ->symbols()     // almeno 1 carattere speciale
                ->uncompromised(), // non presente in leak pubblici (haveibeenpwned)
            'confirmed',
        ];
    }
}
