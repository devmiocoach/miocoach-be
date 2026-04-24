<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Auth\Services\JwtService;
use Illuminate\Support\Facades\Log;

class ForgotPasswordAction
{
    public function __construct(private readonly JwtService $jwt) {}

    public function handle(string $email): void
    {
        $user = User::where('email', mb_strtolower($email))->first();

        if (! $user) {
            // Anti-enumeration: always succeed silently
            Log::info('Password reset requested for unknown email', ['email' => $email]);

            return;
        }

        $token = $this->jwt->generatePasswordResetToken($user->email);

        $user->sendPasswordResetNotification($token);
    }
}
