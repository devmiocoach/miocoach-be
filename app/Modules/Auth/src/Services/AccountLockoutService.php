<?php

namespace App\Modules\Auth\Services;

use Illuminate\Support\Facades\Cache;

class AccountLockoutService
{
    private const MAX_FAILURES    = 5;  // spec: 5 failures
    private const LOCKOUT_MINUTES = 15; // spec: 15 minutes
    private const PREFIX          = 'account_lockout:';

    public function increment(string $email): void
    {
        $key = $this->key($email);
        $ttl = now()->addMinutes(self::LOCKOUT_MINUTES);

        // add() è atomico: crea la chiave solo se non esiste, evitando la race condition
        // tra increment() e il successivo put() con TTL.
        Cache::add($key, 0, $ttl);
        Cache::increment($key);
    }

    public function isLocked(string $email): bool
    {
        return Cache::get($this->key($email), 0) >= self::MAX_FAILURES;
    }

    public function availableIn(string $email): int
    {
        return Cache::getTimeToLive($this->key($email)) ?? 0;
    }

    public function clear(string $email): void
    {
        Cache::forget($this->key($email));
    }

    private function key(string $email): string
    {
        return self::PREFIX . mb_strtolower($email) . '|' . request()->ip();
    }
}
