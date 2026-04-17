<?php

namespace App\Modules\Auth\Services;

use Illuminate\Support\Facades\Cache;

class TokenBlacklistService
{
    private const TTL_HOURS = 24;
    private const PREFIX    = 'token_blacklist:';

    public function add(string $token): void
    {
        Cache::put(
            self::PREFIX . hash('sha256', $token),
            true,
            now()->addHours(self::TTL_HOURS),
        );
    }

    public function isBlacklisted(string $token): bool
    {
        return Cache::has(self::PREFIX . hash('sha256', $token));
    }
}
