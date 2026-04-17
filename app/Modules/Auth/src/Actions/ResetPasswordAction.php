<?php

namespace App\Modules\Auth\Actions;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResetPasswordAction
{
    public function handle(array $data): void
    {
        $status = Password::reset(
            $data,
            function ($user, string $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            // Messaggio generico per evitare user enumeration:
            // non distingue INVALID_USER da INVALID_TOKEN
            Log::info('Password reset failed', ['status' => $status, 'email' => $data['email'] ?? null]);

            throw ValidationException::withMessages([
                'token' => ['Il link di reset non è valido o è scaduto. Richiedi un nuovo link.'],
            ]);
        }
    }
}
