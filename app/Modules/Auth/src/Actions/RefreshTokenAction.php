<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Auth\Services\JwtService;
use App\Modules\Auth\Services\RefreshTokenService;
use Illuminate\Validation\ValidationException;

class RefreshTokenAction
{
    public function __construct(
        private readonly JwtService          $jwt,
        private readonly RefreshTokenService $refreshTokens,
    ) {}

    public function handle(User $user, ?string $currentRefreshToken, ?string $deviceName = null): array
    {
        if (empty($currentRefreshToken)) {
            throw ValidationException::withMessages([
                'refresh_token' => ['Refresh token mancante.'],
            ]);
        }

        $newRefreshToken = $this->refreshTokens->rotate($currentRefreshToken, $user, $deviceName);
        $accessToken     = $this->jwt->generateAccessToken($user);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $newRefreshToken,
        ];
    }
}
