<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class LogoutAction
{
    public function handle(User $user, string $deviceType = 'web'): void
    {
        if ($deviceType === 'mobile') {
            $user->currentAccessToken()?->delete();
            return;
        }

        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();
    }
}
