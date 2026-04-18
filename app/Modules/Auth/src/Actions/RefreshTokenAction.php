<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Auth\Services\TokenBlacklistService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefreshTokenAction
{
    public function __construct(
        private readonly TokenBlacklistService $blacklist,
    ) {}

    public function handle(User $user, ?string $currentToken, ?string $deviceName = null): array
    {
        if (empty($currentToken)) {
            throw ValidationException::withMessages([
                'token' => ['Bearer token mancante nella richiesta.'],
            ]);
        }

        if ($this->blacklist->isBlacklisted($currentToken)) {
            throw ValidationException::withMessages([
                'token' => ['Token non valido o già revocato.'],
            ]);
        }

        $accessToken = $user->currentAccessToken();

        if (! $accessToken) {
            throw ValidationException::withMessages([
                'token' => ['Token non valido.'],
            ]);
        }

        // Delete old and create new atomically; blacklist AFTER success so that
        // a DB failure doesn't leave the user locked out with no valid token.
        $newToken = DB::transaction(function () use ($user, $accessToken, $deviceName) {
            $accessToken->delete();

            return $user->createToken($deviceName ?? 'mobile-device');
        });

        $this->blacklist->add($currentToken);

        return [
            'access_token' => $newToken->plainTextToken,
            'token_type'   => 'Bearer',
        ];
    }
}
