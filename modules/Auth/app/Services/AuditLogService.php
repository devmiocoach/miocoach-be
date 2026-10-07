<?php

namespace Modules\Auth\Services;

use Modules\Auth\Models\AuditLog;

class AuditLogService
{
    public const LOGIN_SUCCESS = 'LOGIN_SUCCESS';

    public const LOGIN_FAILED = 'LOGIN_FAILED';

    public const LOGOUT = 'LOGOUT';

    public const PASSWORD_RESET = 'PASSWORD_RESET';

    public const TWO_FA_SUCCESS = 'TWO_FA_SUCCESS';

    public const TWO_FA_FAILED = 'TWO_FA_FAILED';

    public const TWO_FA_DISABLED = 'TWO_FA_DISABLED';

    public const TWO_FA_BACKUP_REGENERATED = 'TWO_FA_BACKUP_REGENERATED';

    public const TWO_FA_RECOVERY_DISABLED = 'TWO_FA_RECOVERY_DISABLED';

    public function log(string $event, ?int $userId = null, array $metadata = []): void
    {
        AuditLog::create([
            'user_id' => $userId,
            'event' => $event,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata ?: null,
        ]);
    }
}
