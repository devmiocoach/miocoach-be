<?php

namespace App\Modules\Users\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPIva implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\d{11}$/', $value)) {
            $fail('La Partita IVA deve essere composta da 11 cifre numeriche.');

            return;
        }

        // Luhn-like check digit algorithm for Italian P.IVA
        $digits = array_map('intval', str_split($value));
        $sum = 0;

        for ($i = 0; $i < 10; $i++) {
            if ($i % 2 === 0) {
                $sum += $digits[$i];
            } else {
                $val = $digits[$i] * 2;
                $sum += $val >= 10 ? $val - 9 : $val;
            }
        }

        $check = (10 - ($sum % 10)) % 10;

        if ($check !== $digits[10]) {
            $fail('La Partita IVA non è valida (cifra di controllo errata).');
        }
    }
}
