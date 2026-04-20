<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Auth\Notifications\EmailVerificationNotification;
use App\Modules\Auth\Services\JwtService;
use Firebase\JWT\ExpiredException;
use Illuminate\Auth\Events\Verified;
use Illuminate\Validation\ValidationException;

class VerifyEmailAction
{
    public function __construct(private readonly JwtService $jwt) {}

    /**
     * Decodes the JWT verification token and marks the user as verified.
     *
     * @return User the verified user (for redirect)
     * @throws ValidationException on invalid/expired token
     */
    public function handle(string $token): User
    {
        try {
            $payload = $this->jwt->decode($token);
        } catch (ExpiredException) {
            throw ValidationException::withMessages([
                'token' => ['Il link di verifica è scaduto. Richiedine uno nuovo.'],
            ]);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'token' => ['Il link di verifica non è valido.'],
            ]);
        }

        if (($payload->purpose ?? '') !== 'email_verify') {
            throw ValidationException::withMessages([
                'token' => ['Il link di verifica non è valido.'],
            ]);
        }

        $user = User::findOrFail((int) $payload->sub);

        if (sha1($user->email) !== $payload->email_hash) {
            throw ValidationException::withMessages([
                'token' => ['Il link di verifica non è valido.'],
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return $user;
    }

    public function resend(User $user): void
    {
        if (! $user->hasVerifiedEmail()) {
            $user->notify(new EmailVerificationNotification());
        }
    }
}
