<?php

namespace App\Modules\Auth\Services;

use Illuminate\Support\Facades\Cache;

class AccountLockoutService
{
    private const MAX_FAILURES    = 5;
    private const LOCKOUT_MINUTES = 15;
    private const PREFIX          = 'account_lockout:';

    public function increment(string $email): void
    {
        $key    = $this->key($email);
        $expiry = now()->addMinutes(self::LOCKOUT_MINUTES);

        Cache::add($key . ':exp', $expiry->timestamp, $expiry);
        Cache::add($key, 0, $expiry);
        Cache::increment($key);
    }

    public function isLocked(string $email): bool
    {
        return Cache::get($this->key($email), 0) >= self::MAX_FAILURES;
    }

    public function availableIn(string $email): int
    {
        $exp = Cache::get($this->key($email) . ':exp');

        return $exp ? max(0, $exp - time()) : 0;
    }

    public function clear(string $email): void
    {
        Cache::forget($this->key($email));
        Cache::forget($this->key($email) . ':exp');
    }

    private function key(string $email): string
    {
        return self::PREFIX . mb_strtolower($email) . '|' . request()->ip();
    }
}
