<?php

namespace Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Actions\ConfirmTwoFactorAction;
use Modules\Auth\Actions\DisableTwoFactorAction;
use Modules\Auth\Actions\EnableTwoFactorAction;
use Modules\Auth\Actions\RegenerateBackupCodesAction;
use Modules\Auth\Http\Requests\TwoFactorRequest;

class TwoFactorController extends Controller
{
    use ApiResponse;

    public function enable(Request $request, EnableTwoFactorAction $action): JsonResponse
    {
        if ($request->user()->hasEnabledTwoFactorAuthentication()) {
            return response()->json(['message' => '2FA è già abilitato sul tuo account.'], 409);
        }

        $backupCodes = $action->handle($request->user());

        return $this->success([
            'qr_code_url' => $request->user()->twoFactorQrCodeSvg(),
            'backup_codes' => $backupCodes,
        ]);
    }

    public function confirm(TwoFactorRequest $request, ConfirmTwoFactorAction $action): JsonResponse
    {
        $action->handle($request->user(), $request->code);

        return $this->message('TWO_FA_CONFIRMED', '2FA confermato e attivo.');
    }

    public function disable(Request $request, DisableTwoFactorAction $action): JsonResponse
    {
        $rateLimitKey = '2fa-disable:'.$request->user()->id;

        if (RateLimiter::tooManyAttempts($rateLimitKey, maxAttempts: 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            throw ValidationException::withMessages([
                'password' => ["Troppi tentativi. Riprova tra {$seconds} secondi."],
            ]);
        }

        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($request->password, $request->user()->password)) {
            RateLimiter::hit($rateLimitKey, decay: 60 * 15);
            throw ValidationException::withMessages([
                'password' => ['Password non corretta.'],
            ]);
        }

        RateLimiter::clear($rateLimitKey);
        $action->handle($request->user());

        $request->user()->backupCodes()->delete();

        return $this->message('TWO_FA_DISABLED', '2FA disabilitato.');
    }

    public function regenerateCodes(Request $request, RegenerateBackupCodesAction $action): JsonResponse
    {
        if (! $request->user()->hasConfirmedTwoFactorAuthentication()) {
            return response()->json(['message' => '2FA non è configurato.'], 409);
        }

        $backupCodes = $action->handle($request->user());

        return response()->json([
            'backup_codes' => $backupCodes,
        ]);
    }
}
