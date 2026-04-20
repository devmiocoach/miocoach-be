<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Auth\Exceptions\AccountLockedException;
use App\Modules\Auth\Notifications\NewDeviceLoginNotification;
use App\Modules\Auth\Services\AccountLockoutService;
use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Auth\Services\JwtService;
use App\Modules\Auth\Services\RateLimiterService;
use App\Modules\Auth\Services\RefreshTokenService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class LoginAction
{
    public function __construct(
        private readonly RateLimiterService   $rateLimiter,
        private readonly AccountLockoutService $lockout,
        private readonly JwtService           $jwt,
        private readonly RefreshTokenService  $refreshTokens,
        private readonly AuditLogService      $audit,
    ) {}

    public function handle(string $email, string $password, ?string $deviceName = null): array
    {
        $this->rateLimiter->check($email);

        if ($this->lockout->isLocked($email)) {
            event(new Lockout(request()));
            throw new AccountLockedException($this->lockout->availableIn($email));
        }

        $email = mb_strtolower($email);

        if (! Auth::attempt(['email' => $email, 'password' => $password])) {
            $this->rateLimiter->hit($email);
            $this->lockout->increment($email);
            $this->audit->log(AuditLogService::LOGIN_FAILED, null, ['email' => $email]);

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $this->rateLimiter->clear($email);
        $this->lockout->clear($email);

        /** @var User $user */
        $user = Auth::user();
        Auth::logout(); // stateless — no session

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $tempToken = $this->jwt->generateTempToken($user);

            return ['two_factor_required' => true, 'temp_token' => $tempToken];
        }

        $this->notifyNewDeviceIfNeeded($user);

        $accessToken  = $this->jwt->generateAccessToken($user);
        $refreshToken = $this->refreshTokens->create($user, $deviceName);

        $this->audit->log(AuditLogService::LOGIN_SUCCESS, $user->id);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
        ];
    }

    private function notifyNewDeviceIfNeeded(User $user): void
    {
        $ip       = request()->ip();
        $ua       = request()->userAgent() ?? 'unknown';
        $cacheKey = 'known_device:' . $user->id . ':' . hash('sha256', $ip . '|' . $ua);

        if (! Cache::has($cacheKey)) {
            Cache::put($cacheKey, true, now()->addDays(30));
            $user->notify(new NewDeviceLoginNotification($ip, $ua));
        }
    }
}
