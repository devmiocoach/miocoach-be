<?php

namespace App\Modules\Auth\Services;

use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\ValidationException;

class RateLimiterService
{
    private const MAX_ATTEMPTS = 5;
    private const DECAY_MINUTES = 15;

    public function __construct(
        private readonly RateLimiter $limiter,
    ) {}

    public function check(string $email): void
    {
        if ($this->limiter->tooManyAttempts($this->key($email), self::MAX_ATTEMPTS)) {
            $seconds = $this->limiter->availableIn($this->key($email));

            throw ValidationException::withMessages([
                'email' => [Lang::get('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ])],
            ]);
        }
    }

    public function hit(string $email): void
    {
        $this->limiter->hit($this->key($email), self::DECAY_MINUTES * 60);
    }

    public function clear(string $email): void
    {
        $this->limiter->clear($this->key($email));
    }

    private function key(string $email): string
    {
        return 'login:' . mb_strtolower($email) . '|' . request()->ip();
    }
}
