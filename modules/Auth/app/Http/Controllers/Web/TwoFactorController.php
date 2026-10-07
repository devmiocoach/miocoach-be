<?php

namespace Modules\Auth\Http\Controllers\Web;

use App\Helpers\Toast;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Modules\Auth\Actions\ConfirmTwoFactorAction;
use Modules\Auth\Actions\DisableTwoFactorAction;
use Modules\Auth\Actions\EnableTwoFactorAction;
use Modules\Auth\Actions\RegenerateBackupCodesAction;
use Modules\Auth\Http\Requests\TwoFactorChallengeRequest;
use Modules\Auth\Http\Requests\TwoFactorRequest;
use Modules\Auth\Services\AuditLogService;

class TwoFactorController extends Controller
{
    /**
     * Show 2FA enable page — generates secret and QR code.
     */
    public function enable(Request $request, EnableTwoFactorAction $action): Response|RedirectResponse
    {
        if ($request->user()->hasEnabledTwoFactorAuthentication()) {
            return redirect()->route('two-factor.confirm.show')
                ->with('error', __('2FA è già in fase di configurazione.'));
        }

        $backupCodes = $action->handle($request->user());

        return Inertia::render('Auth::TwoFactorEnable', [
            'qr_code_svg' => $request->user()->twoFactorQrCodeSvg(),
            'backup_codes' => $backupCodes,
        ]);
    }

    /**
     * Show 2FA confirmation page — user enters TOTP code.
     */
    public function showConfirm(Request $request): Response
    {
        return Inertia::render('Auth::TwoFactorConfirm', [
            'two_factor_enabled' => $request->user()->hasEnabledTwoFactorAuthentication(),
        ]);
    }

    /**
     * Confirm 2FA with TOTP code.
     */
    public function confirm(TwoFactorRequest $request, ConfirmTwoFactorAction $action): RedirectResponse
    {
        try {
            $action->handle($request->user(), $request->code);
        } catch (ValidationException $e) {
            return redirect()->route('two-factor.confirm.show')
                ->withErrors(['code' => __('Codice 2FA non valido. Riprova.')]);
        }

        Toast::success(__('2FA confermato e attivato con successo.'));

        return redirect()->route('dashboard');
    }

    /**
     * Disable 2FA.
     */
    public function disable(Request $request, DisableTwoFactorAction $action, AuditLogService $audit): RedirectResponse
    {
        $rateLimitKey = '2fa-disable:'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($rateLimitKey, maxAttempts: 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            throw ValidationException::withMessages([
                'password' => [__("Troppi tentativi. Riprova tra {$seconds} secondi.")],
            ]);
        }

        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($request->password, $request->user()->password)) {
            RateLimiter::hit($rateLimitKey, 60 * 15);
            throw ValidationException::withMessages([
                'password' => [__('Password non corretta.')],
            ]);
        }

        RateLimiter::clear($rateLimitKey);
        $action->handle($request->user());
        $request->user()->backupCodes()->delete();

        $audit->log(AuditLogService::TWO_FA_DISABLED, $request->user()->id);

        Toast::success(__('2FA disabilitato con successo.'));

        return redirect()->route('settings.security');
    }

    /**
     * Regenerate backup codes — invalidates all existing codes.
     */
    public function regenerateCodes(Request $request, RegenerateBackupCodesAction $action): RedirectResponse
    {
        if (! $request->user()->hasConfirmedTwoFactorAuthentication()) {
            return redirect()->route('settings.security')
                ->with('error', __('2FA non è configurato sul tuo account.'));
        }

        $backupCodes = $action->handle($request->user());

        Toast::success(__('Nuovi codici di backup generati. Conservali in un luogo sicuro.'));

        return redirect()->route('two-factor.regenerate-codes.show')
            ->with('backup_codes', $backupCodes);
    }

    /**
     * Show regenerated backup codes page.
     */
    public function showRegeneratedCodes(Request $request): Response
    {
        $backupCodes = session('backup_codes', []);

        return Inertia::render('Auth::TwoFactorRegenerateCodes', [
            'backup_codes' => $backupCodes,
        ]);
    }

    /**
     * Show 2FA challenge page during login.
     */
    public function showChallenge(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('two_factor_pending_user_id')) {
            return redirect()->route('login')
                ->with('error', __('Sessione 2FA non trovata. Effettua nuovamente il login.'));
        }

        return Inertia::render('Auth::TwoFactorChallenge');
    }

    /**
     * Handle 2FA challenge during login.
     */
    public function challenge(TwoFactorChallengeRequest $request): RedirectResponse
    {
        $userId = $request->session()->get('two_factor_pending_user_id');

        if (! $userId) {
            return redirect()->route('login')
                ->with('error', __('Sessione 2FA scaduta. Effettua nuovamente il login.'));
        }

        $rateLimitKey = "2fa-challenge:{$userId}";

        if (RateLimiter::tooManyAttempts($rateLimitKey, maxAttempts: 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            throw ValidationException::withMessages([
                'code' => [__("Troppi tentativi. Riprova tra {$seconds} secondi.")],
            ]);
        }

        $user = User::findOrFail($userId);

        if ($user->two_factor_secret === null) {
            $request->session()->forget('two_factor_pending_user_id');

            return redirect()->route('login')
                ->with('error', __('2FA non abilitato su questo account.'));
        }

        try {
            $secret = decrypt($user->two_factor_secret);
        } catch (DecryptException) {
            throw ValidationException::withMessages([
                'code' => [__('Errore di configurazione 2FA. Contatta il supporto.')],
            ]);
        }

        $totp = app(TwoFactorAuthenticationProvider::class);
        $valid = $totp->verify($secret, $request->code);

        if (! $valid) {
            if (! $this->consumeBackupCode($user, $request->code)) {
                RateLimiter::hit($rateLimitKey, 60 * 15);
                throw ValidationException::withMessages([
                    'code' => [__('Codice 2FA non valido.')],
                ]);
            }
        }

        RateLimiter::clear($rateLimitKey);
        $request->session()->forget('two_factor_pending_user_id');

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        Toast::default(
            __('auth.welcome-back', ['name' => $user->name]),
        );

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Attempt to consume a backup code for 2FA.
     */
    private function consumeBackupCode(User $user, string $code): bool
    {
        $hash = hash('sha256', strtoupper(trim($code)));

        $backupCode = $user->backupCodes()
            ->where('code_hash', $hash)
            ->whereNull('used_at')
            ->first();

        if (! $backupCode) {
            return false;
        }

        $backupCode->update(['used_at' => now()]);

        return true;
    }
}
