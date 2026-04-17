<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Auth\Notifications\EmailVerificationNotification;
use Illuminate\Auth\Events\Verified;

class VerifyEmailAction
{
    public function handle(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return true;
    }

    public function resend(User $user): void
    {
        if (! $user->hasVerifiedEmail()) {
            // Usa la notification custom con la route corretta (auth.verification.verify)
            // e non quella default di Laravel (che punterebbe a verification.verify inesistente)
            $user->notify(new EmailVerificationNotification());
        }
    }
}
