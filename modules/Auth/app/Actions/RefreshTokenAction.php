<?php

namespace Modules\Auth\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Auth\Services\JwtService;
use Modules\Auth\Services\RefreshTokenService;

class RefreshTokenAction
{
    public function __construct(
        private readonly JwtService $jwt,
        private readonly RefreshTokenService $refreshTokens,
    ) {}

    public function handle(?string $currentRefreshToken, ?string $deviceName = null): array
    {
        if (empty($currentRefreshToken)) {
            throw ValidationException::withMessages([
                'refresh_token' => ['Refresh token mancante.'],
            ]);
        }

        // The owner is resolved from the refresh token itself, so this works
        // even when the access token has already expired (mobile clients).
        $rotated = $this->refreshTokens->rotateByToken($currentRefreshToken, $deviceName);
        $user = $rotated['user'];

        return [
            'access_token' => $this->jwt->generateAccessToken($user),
            'refresh_token' => $rotated['refresh_token'],
            'user' => $user,
        ];
    }
}
