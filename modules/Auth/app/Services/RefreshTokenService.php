<?php

namespace Modules\Auth\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Models\RefreshToken;

class RefreshTokenService
{
    private const TOKEN_BYTES = 64;

    private const TTL_DAYS = 30;

    public function create(User $user, ?string $deviceName = null): string
    {
        $plaintext = bin2hex(random_bytes(self::TOKEN_BYTES));

        RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plaintext),
            'device_name' => $deviceName,
            'expires_at' => now()->addDays(self::TTL_DAYS),
        ]);

        return $plaintext;
    }

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

    /**
     * Rotate a refresh token resolving the owner from the token itself.
     *
     * Used by the mobile/API refresh flow, where the access token may already
     * be expired (so there is no authenticated user on the request). The
     * opaque refresh token is the sole credential; its sha256 hash is unique.
     *
     * @return array{user: User, refresh_token: string}
     */
    public function rotateByToken(string $plaintext, ?string $deviceName = null): array
    {
        $existing = RefreshToken::where('token_hash', hash('sha256', $plaintext))->first();

        if (! $existing || ! $existing->isValid()) {
            throw ValidationException::withMessages([
                'refresh_token' => ['Refresh token non valido o scaduto.'],
            ]);
        }

        $existing->update(['revoked_at' => now()]);

        $user = User::findOrFail($existing->user_id);
        $newToken = $this->create($user, $deviceName ?? $existing->device_name);

        return ['user' => $user, 'refresh_token' => $newToken];
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
