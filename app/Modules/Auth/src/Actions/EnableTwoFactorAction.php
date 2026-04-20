<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Auth\Models\TwoFactorBackupCode;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;

class EnableTwoFactorAction
{
    private const BACKUP_CODE_COUNT = 8;

    public function __construct(
        private readonly EnableTwoFactorAuthentication $fortifyAction,
    ) {}

    /**
     * Enables 2FA and generates 8 one-time backup codes.
     * Returns the plaintext codes (shown once; only hashes stored in DB).
     *
     * @return string[] plaintext backup codes
     */
    public function handle(User $user): array
    {
        ($this->fortifyAction)($user);

        // Delete any existing backup codes (re-setup)
        $user->backupCodes()->delete();

        $plaintextCodes = [];

        for ($i = 0; $i < self::BACKUP_CODE_COUNT; $i++) {
            $code = strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
            $plaintextCodes[] = $code;

            TwoFactorBackupCode::create([
                'user_id'   => $user->id,
                'code_hash' => hash('sha256', $code),
            ]);
        }

        return $plaintextCodes;
    }
}
