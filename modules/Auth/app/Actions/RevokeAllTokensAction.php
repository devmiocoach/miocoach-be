<?php

namespace Modules\Auth\Actions;

use App\Models\User;
use Modules\Auth\Services\RefreshTokenService;

class RevokeAllTokensAction
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokens,
    ) {}

    public function handle(User $user): void
    {
        $this->refreshTokens->revokeAll($user);
    }
}
