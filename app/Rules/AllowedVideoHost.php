<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AllowedVideoHost implements ValidationRule
{
    /** @param  Closure(string): void  $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('validation.custom.video_url.allowed_host')->translate();

            return;
        }

        $parts = parse_url($value);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            $fail('validation.custom.video_url.allowed_host')->translate();

            return;
        }

        /** @var array<int, string> $allowed */
        $allowed = config('workoutplans.video_hosts', []);

        if ($parts['scheme'] !== 'https' || ! in_array(strtolower($parts['host']), $allowed, true)) {
            $fail('validation.custom.video_url.allowed_host')->translate();
        }
    }
}
