<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Auth\Models\RefreshToken;
use Illuminate\Validation\ValidationException;

class RefreshTokenService
{
    private const TOKEN_BYTES = 64;
    private const TTL_DAYS    = 30;

    public function create(User $user, ?string $deviceName = null): string
    {
        $plaintext = bin2hex(random_bytes(self::TOKEN_BYTES));

        RefreshToken::create([
            'user_id'     => $user->id,
            'token_hash'  => hash('sha256', $plaintext),
            'device_name' => $deviceName,
            'expires_at'  => now()->addDays(self::TTL_DAYS),
        ]);

        return $plaintext;
    }

    /**
     * Rotate: revoke old token, issue new one.
     *
     * @throws ValidationException if token is invalid/expired/revoked
     */
    public function rotate(string $plaintext, User $user, ?string $deviceName = null): string
    {
        $existing = RefreshToken::where('token_hash', hash('sha256', $plaintext))
            ->where('user_id', $user->id)
            ->first();

        if (! $existing || ! $existing->isValid()) {
            throw ValidationException::withMessages([
                'refresh_token' => ['Refresh token non valido o scaduto.'],
            ]);
        }

        $existing->update(['revoked_at' => now()]);

        return $this->create($user, $deviceName ?? $existing->device_name);
    }

    public function revoke(string $plaintext): void
    {
        RefreshToken::where('token_hash', hash('sha256', $plaintext))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function revokeAll(User $user): void
    {
        $user->refreshTokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    public function findValid(string $plaintext, User $user): ?RefreshToken
    {
        $token = RefreshToken::where('token_hash', hash('sha256', $plaintext))
            ->where('user_id', $user->id)
            ->first();

        return ($token && $token->isValid()) ? $token : null;
    }
}
