<?php

namespace Modules\Auth\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Modules\Auth\Notifications\ResetPasswordNotification;
use Modules\Auth\Services\JwtService;

class ForgotPasswordAction
{
    public function __construct(
        private readonly JwtService $jwt,
    ) {}

    public function handle(string $email): void
    {
        $user = User::where('email', mb_strtolower($email))->first();

        if (! $user) {
            Log::info('Password reset requested for unknown email', ['email' => $email]);

            return;
        }

        $token = $this->jwt->generatePasswordResetToken($user->email);

        // Not sendPasswordResetNotification(): that one builds a web link for
        // broker tokens, and this token is a JWT the client app has to post
        // back to /api/v1/auth/reset-password.
        $user->notify(ResetPasswordNotification::forClientApp($token, $user->email));
    }
}
