<?php

namespace Modules\Settings\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController
{
    /**
     * Show the security settings page.
     */
    public function index(): Response
    {
        $user = Auth::user();

        return Inertia::render('Settings::Security', [
            'two_factor_enabled' => $user->hasConfirmedTwoFactorAuthentication(),
        ]);
    }
}
