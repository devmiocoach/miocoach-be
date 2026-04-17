<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Auth\Notifications\NewDeviceLoginNotification;
use App\Modules\Auth\Services\AccountLockoutService;
use App\Modules\Auth\Services\RateLimiterService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class LoginAction
{
    public function __construct(
        private readonly RateLimiterService $rateLimiter,
        private readonly AccountLockoutService $lockout,
    ) {}

    public function handle(string $email, string $password, string $deviceType = 'web', ?string $deviceName = null): array
    {
        $this->rateLimiter->check($email);

        if ($this->lockout->isLocked($email)) {
            event(new Lockout(request()));
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => $this->lockout->availableIn($email)]),
            ]);
        }

        $email = mb_strtolower($email);

        if (! Auth::attempt(['email' => $email, 'password' => $password])) {
            $this->rateLimiter->hit($email);
            $this->lockout->increment($email);

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $this->rateLimiter->clear($email);
        $this->lockout->clear($email);

        /** @var User $user */
        $user = Auth::user();

        if ($user->hasEnabledTwoFactorAuthentication()) {
            Auth::logout();
            return ['two_factor_required' => true, 'email' => $email];
        }

        $this->notifyNewDeviceIfNeeded($user);

        if ($deviceType === 'mobile') {
            $token = $user->createToken($deviceName ?? 'mobile-device');
            return [
                'access_token' => $token->plainTextToken,
                'token_type'   => 'Bearer',
                'expires_in'   => null,
                'user'         => ['id' => $user->id, 'role' => $user->getRoleNames()->first()],
            ];
        }

        session()->regenerate();
        return [
            'user' => ['id' => $user->id, 'role' => $user->getRoleNames()->first()],
        ];
    }

    private function notifyNewDeviceIfNeeded(User $user): void
    {
        $ip        = request()->ip();
        $userAgent = request()->userAgent() ?? 'unknown';
        // Chiave univoca per IP + user-agent: notifica una volta ogni 30 giorni per coppia
        $cacheKey  = 'known_device:' . $user->id . ':' . hash('sha256', $ip . '|' . $userAgent);

        if (! Cache::has($cacheKey)) {
            Cache::put($cacheKey, true, now()->addDays(30));
            $user->notify(new NewDeviceLoginNotification($ip, $userAgent));
        }
    }
}
