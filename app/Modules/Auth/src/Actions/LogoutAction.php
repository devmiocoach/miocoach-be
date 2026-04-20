<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Auth\Services\RefreshTokenService;

class LogoutAction
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokens,
        private readonly AuditLogService     $audit,
    ) {}

    public function handle(User $user, ?string $refreshToken): void
    {
        if ($refreshToken) {
            $this->refreshTokens->revoke($refreshToken);
        }

        $this->audit->log(AuditLogService::LOGOUT, $user->id);
    }
}
