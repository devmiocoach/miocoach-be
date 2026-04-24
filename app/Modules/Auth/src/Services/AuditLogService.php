<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Models\AuditLog;

class AuditLogService
{
    public const LOGIN_SUCCESS  = 'LOGIN_SUCCESS';
    public const LOGIN_FAILED   = 'LOGIN_FAILED';
    public const LOGOUT         = 'LOGOUT';
    public const PASSWORD_RESET = 'PASSWORD_RESET';
    public const TWO_FA_SUCCESS = 'TWO_FA_SUCCESS';
    public const TWO_FA_FAILED  = 'TWO_FA_FAILED';

    public function log(string $event, ?int $userId = null, array $metadata = []): void
    {
        AuditLog::create([
            'user_id'    => $userId,
            'event'      => $event,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata'   => $metadata ?: null,
        ]);
    }
}
