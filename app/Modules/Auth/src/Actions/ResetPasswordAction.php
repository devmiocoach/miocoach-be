<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Auth\Services\JwtService;
use App\Modules\Auth\Services\RefreshTokenService;
use Firebase\JWT\ExpiredException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResetPasswordAction
{
    public function __construct(
        private readonly JwtService          $jwt,
        private readonly RefreshTokenService $refreshTokens,
        private readonly AuditLogService     $audit,
    ) {}

    public function handle(array $data): void
    {
        // Decode JWT reset token
        try {
            $payload = $this->jwt->decode($data['token']);
        } catch (ExpiredException) {
            throw ValidationException::withMessages([
                'token' => ['Il link di reset è scaduto. Richiedi un nuovo link.'],
            ]);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'token' => ['Il link di reset non è valido.'],
            ]);
        }

        if (($payload->purpose ?? '') !== 'password_reset') {
            throw ValidationException::withMessages([
                'token' => ['Il link di reset non è valido.'],
            ]);
        }

        if (mb_strtolower($data['email']) !== mb_strtolower($payload->email)) {
            throw ValidationException::withMessages([
                'email' => ['Email non corrispondente al token di reset.'],
            ]);
        }

        $user = User::where('email', mb_strtolower($data['email']))->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'token' => ['Il link di reset non è valido.'],
            ]);
        }

        $user->forceFill([
            'password'       => Hash::make($data['password']),
            'remember_token' => Str::random(60),
        ])->save();

        // Revoke ALL existing refresh tokens (attacker loses access)
        $this->refreshTokens->revokeAll($user);

        event(new PasswordReset($user));

        $this->audit->log(AuditLogService::PASSWORD_RESET, $user->id);
    }
}
