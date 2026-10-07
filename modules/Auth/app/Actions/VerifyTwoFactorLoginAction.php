<?php

namespace Modules\Auth\Actions;

use App\Models\User;
use Firebase\JWT\ExpiredException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Modules\Auth\Models\TwoFactorBackupCode;
use Modules\Auth\Services\AuditLogService;
use Modules\Auth\Services\JwtService;
use Modules\Auth\Services\RefreshTokenService;

class VerifyTwoFactorLoginAction
{
    public function __construct(
        private readonly JwtService $jwt,
        private readonly RefreshTokenService $refreshTokens,
        private readonly AuditLogService $audit,
        private readonly TwoFactorAuthenticationProvider $totp,
    ) {}

    public function handle(string $tempToken, string $code, ?string $deviceName = null): array
    {
        try {
            $payload = $this->jwt->decode($tempToken);
        } catch (ExpiredException) {
            throw ValidationException::withMessages([
                'temp_token' => ['Token scaduto. Effettua nuovamente il login.'],
            ]);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'temp_token' => ['Token non valido.'],
            ]);
        }

        if (empty($payload->two_fa_pending)) {
            throw ValidationException::withMessages([
                'temp_token' => ['Token non valido.'],
            ]);
        }

        $user = User::findOrFail((int) $payload->sub);

        if ($user->two_factor_secret === null) {
            throw ValidationException::withMessages([
                'code' => ['2FA non abilitato su questo account.'],
            ]);
        }

        try {
            $secret = decrypt($user->two_factor_secret);
        } catch (DecryptException) {
            throw ValidationException::withMessages([
                'code' => ['Errore di configurazione 2FA. Contatta il supporto.'],
            ]);
        }

        $totpValid = $this->totp->verify($secret, $code);

        if (! $totpValid) {
            if (! $this->consumeBackupCode($user, $code)) {
                $this->audit->log(AuditLogService::TWO_FA_FAILED, $user->id);

                throw ValidationException::withMessages([
                    'code' => ['Codice 2FA non valido.'],
                ]);
            }
        }

        $accessToken = $this->jwt->generateAccessToken($user);
        $refreshToken = $this->refreshTokens->create($user, $deviceName);

        $this->audit->log(AuditLogService::LOGIN_SUCCESS, $user->id);
        $this->audit->log(AuditLogService::TWO_FA_SUCCESS, $user->id);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'user' => $user,
        ];
    }

    private function consumeBackupCode(User $user, string $code): bool
    {
        $hash = hash('sha256', strtoupper(trim($code)));

        $backupCode = TwoFactorBackupCode::where('user_id', $user->id)
            ->where('code_hash', $hash)
            ->whereNull('used_at')
            ->first();

        if (! $backupCode) {
            return false;
        }

        $backupCode->update(['used_at' => now()]);

        return true;
    }
}
