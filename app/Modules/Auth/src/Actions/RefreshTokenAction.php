<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Auth\Services\TokenBlacklistService;
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

        $this->blacklist->add($currentToken);

        $user->currentAccessToken()->delete();
        $newToken = $user->createToken($deviceName ?? 'mobile-device');

        return [
            'access_token' => $newToken->plainTextToken,
            'token_type'   => 'Bearer',
        ];
    }
}
