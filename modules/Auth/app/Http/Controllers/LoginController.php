<?php

namespace Modules\Auth\Http\Controllers;

use App\Helpers\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Http\Requests\LoginRequest;

class LoginController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth::Login', [
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     * Login
     */
    public function store(LoginRequest $request)
    {
        try {
            $user = $request->validateCredentials();
        } catch (AuthException $e) {
            return back()->with(['error' => $e->getMessage()]);
        }

        if ($user->type && $user->type->value !== $request->input('type')) {
            $message = $request->input('type') === 'coach'
                ? __('auth.not_a_coach')
                : __('auth.not_an_athlete');

            return back()->with(['error' => $message]);
        }

        // Check if user has confirmed 2FA
        if ($user->hasConfirmedTwoFactorAuthentication()) {
            $request->session()->regenerate();
            $request->session()->put('two_factor_pending_user_id', $user->id);
            $request->session()->put('two_factor_pending_remember', $request->boolean('remember'));

            return redirect()->route('two-factor.challenge.show');
        }

        Auth::login($user, request()->boolean('remember'));

        request()->session()->regenerate();

        Toast::default(
            __('auth.welcome-back', ['name' => $user->name]),
        );

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
