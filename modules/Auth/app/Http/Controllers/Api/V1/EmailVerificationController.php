<?php

namespace Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Actions\VerifyEmailAction;

class EmailVerificationController extends Controller
{
    use ApiResponse;

    /**
     * The verification link in the email points here for both surfaces, so a
     * browser must land on a page instead of raw JSON. API clients keep the
     * JSON contract by asking for it via the Accept header.
     */
    public function verify(Request $request, string $token, VerifyEmailAction $action): JsonResponse|RedirectResponse
    {
        try {
            $action->handle($token);
        } catch (ValidationException $e) {
            if (! $request->expectsJson()) {
                return redirect()->route('login')->with('error', $e->getMessage());
            }

            return response()->json([
                'status' => 'error',
                'code' => 'VERIFICATION_FAILED',
                'message' => $e->getMessage(),
            ], 422);
        }

        if (! $request->expectsJson()) {
            return redirect()->route('login')->with('status', trans('auth.email-verified'));
        }

        return $this->message('EMAIL_VERIFIED', 'Email verificata con successo.');
    }

    public function resend(Request $request, VerifyEmailAction $action): JsonResponse
    {
        $action->resend($request->user());

        return $this->message('VERIFICATION_RESENT', 'Email di verifica reinviata.');
    }
}
