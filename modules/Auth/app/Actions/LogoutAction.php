<?php

namespace Modules\Auth\Actions;

use App\Models\User;
use Modules\Auth\Services\AuditLogService;
use Modules\Auth\Services\RefreshTokenService;
use Modules\Auth\Services\TokenBlacklistService;

class LogoutAction
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokens,
        private readonly TokenBlacklistService $blacklist,
        private readonly AuditLogService $audit,
    ) {}

    public function handle(User $user, ?string $refreshToken, ?string $accessToken): void
    {
        if ($refreshToken) {
            $this->refreshTokens->revoke($refreshToken);
        }

        if ($accessToken) {
            $this->blacklist->add($accessToken);
        }

        $this->audit->log(AuditLogService::LOGOUT, $user->id);
    }
}
