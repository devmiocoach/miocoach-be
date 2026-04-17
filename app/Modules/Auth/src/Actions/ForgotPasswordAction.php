<?php

namespace App\Modules\Auth\Actions;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class ForgotPasswordAction
{
    public function handle(string $email): void
    {
        // Invia il link senza rivelare se l'email esiste o meno (anti user enumeration)
        $status = Password::sendResetLink(['email' => $email]);

        if ($status !== Password::RESET_LINK_SENT) {
            Log::info('Password reset requested for unknown/failed email', ['email' => $email, 'status' => $status]);
        }
    }
}
