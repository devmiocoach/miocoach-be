<?php

namespace App\Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\VerifyEmailAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmailVerificationController extends Controller
{
    public function verify(Request $request, string $token, VerifyEmailAction $action): RedirectResponse
    {
        try {
            $action->handle($token);
        } catch (ValidationException) {
            return redirect(
                rtrim(config('app.frontend_url', config('app.url')), '/') . '/login?verified=false&error=invalid_token'
            );
        }

        return redirect(
            rtrim(config('app.frontend_url', config('app.url')), '/') . '/login?verified=true'
        );
    }

    public function resend(Request $request, VerifyEmailAction $action): JsonResponse
    {
        $action->resend($request->user());

        return response()->json(['message' => 'Email di verifica reinviata.']);
    }
}
