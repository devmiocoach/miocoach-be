<?php

namespace Modules\Auth\Actions;

use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Modules\Auth\Models\TwoFactorBackupCode;

class EnableTwoFactorAction
{
    private const BACKUP_CODE_COUNT = 8;

    public function __construct(
        private readonly EnableTwoFactorAuthentication $fortifyAction,
    ) {}

    public function handle(User $user): array
    {
        ($this->fortifyAction)($user);

        $user->backupCodes()->delete();

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
