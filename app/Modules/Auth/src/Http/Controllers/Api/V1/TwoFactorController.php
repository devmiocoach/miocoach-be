<?php

namespace App\Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\ConfirmTwoFactorAction;
use App\Modules\Auth\Actions\DisableTwoFactorAction;
use App\Modules\Auth\Actions\EnableTwoFactorAction;
use App\Modules\Auth\Http\Requests\TwoFactorRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function enable(Request $request, EnableTwoFactorAction $action): JsonResponse
    {
        if ($request->user()->hasEnabledTwoFactorAuthentication()) {
            return response()->json(['message' => '2FA è già abilitato sul tuo account.'], 409);
        }

        $action->handle($request->user());

        return response()->json([
            'message'        => '2FA abilitato. Scansiona il QR code per configurarlo.',
            'qr_code_svg'    => $request->user()->twoFactorQrCodeSvg(),
            'recovery_codes' => $request->user()->recoveryCodes(),
        ]);
    }

    public function confirm(TwoFactorRequest $request, ConfirmTwoFactorAction $action): JsonResponse
    {
        $action->handle($request->user(), $request->code);

        return response()->json(['message' => '2FA confermato e attivo.']);
    }

    public function disable(Request $request, DisableTwoFactorAction $action): JsonResponse
    {
        $rateLimitKey = '2fa-disable:' . $request->user()->id;

        if (RateLimiter::tooManyAttempts($rateLimitKey, maxAttempts: 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            throw ValidationException::withMessages([
                'password' => ["Troppi tentativi. Riprova tra {$seconds} secondi."],
            ]);
        }

        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($request->password, $request->user()->password)) {
            RateLimiter::hit($rateLimitKey, decay: 60 * 15);
            throw ValidationException::withMessages([
                'password' => ['Password non corretta.'],
            ]);
        }

        RateLimiter::clear($rateLimitKey);
        $action->handle($request->user());

        return response()->json(['message' => '2FA disabilitato.']);
    }
}
