<?php

namespace Modules\Auth\Actions;

use App\Models\User;
use Illuminate\Support\Str;
use Modules\Auth\Models\TwoFactorBackupCode;
use Modules\Auth\Services\AuditLogService;

class RegenerateBackupCodesAction
{
    private const BACKUP_CODE_COUNT = 8;

    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    /**
     * Invalidate all existing backup codes and generate new ones.
     *
     * @return array<int, string> Plaintext backup codes
     */
    public function handle(User $user): array
    {
        $user->backupCodes()->delete();

        $this->audit->log(AuditLogService::TWO_FA_BACKUP_REGENERATED, $user->id);

        $plaintextCodes = [];

        for ($i = 0; $i < self::BACKUP_CODE_COUNT; $i++) {
            $code = strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
            $plaintextCodes[] = $code;

            TwoFactorBackupCode::create([
                'user_id' => $user->id,
                'code_hash' => hash('sha256', $code),
            ]);
        }

        return $plaintextCodes;
    }
}
