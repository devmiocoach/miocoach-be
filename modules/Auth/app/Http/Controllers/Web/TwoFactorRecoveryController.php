<?php

namespace Modules\Auth\Http\Controllers\Web;

use App\Helpers\Toast;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Auth\Notifications\TwoFactorRecoveryNotification;
use Modules\Auth\Services\AuditLogService;

class TwoFactorRecoveryController extends Controller
{
    /**
     * Show the recovery request form.
     */
    public function showRequest(): Response
    {
        return Inertia::render('Auth::TwoFactorRecovery');
    }

    /**
     * Send a recovery email to disable 2FA.
     */
    public function sendRecoveryEmail(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user && $user->hasConfirmedTwoFactorAuthentication()) {
            $token = Str::random(64);
            $hash = hash('sha256', $token);

            Cache::put(
                "2fa_recovery:{$hash}",
                $user->id,
                now()->addHours(24),
            );

            $user->notify(new TwoFactorRecoveryNotification($user, $token));
        }

        Toast::success(__('Se l\'email corrisponde a un account con 2FA abilitato, riceverai un link di recupero.'));

        return redirect()->route('login');
    }

    /**
     * Show recovery confirmation page — user must click button to confirm.
     */
    public function showRecover(string $token): Response|RedirectResponse
    {
        $hash = hash('sha256', $token);

        if (! Cache::has("2fa_recovery:{$hash}")) {
            return redirect()->route('login')
                ->with('error', __('Link di recupero scaduto o non valido. Richiedi un nuovo link.'));
        }

        return Inertia::render('Auth::TwoFactorRecoverConfirm', [
            'token' => $token,
        ]);
    }

    /**
     * Handle the recovery confirmation — disables 2FA.
     */
    public function confirmRecover(string $token, AuditLogService $audit): RedirectResponse
    {
        $hash = hash('sha256', $token);

        $userId = Cache::get("2fa_recovery:{$hash}");

        if (! $userId) {
            return redirect()->route('login')
                ->with('error', __('Link di recupero scaduto o non valido. Richiedi un nuovo link.'));
        }

        $user = User::findOrFail($userId);

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $user->backupCodes()->delete();

        Cache::forget("2fa_recovery:{$hash}");

        $audit->log(AuditLogService::TWO_FA_RECOVERY_DISABLED, $user->id);

        Toast::success(__('2FA disabilitato con successo. Ora puoi accedere con email e password.'));

        return redirect()->route('login');
    }
}
