<?php

namespace App\Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\ForgotPasswordAction;
use App\Modules\Auth\Actions\ResetPasswordAction;
use App\Modules\Auth\Http\Requests\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PasswordController extends Controller
{
    public function forgotPassword(Request $request, ForgotPasswordAction $action): JsonResponse
    {
        $request->validate(['email' => ['required', 'email:rfc,dns', 'max:255']]);

        $action->handle($request->email);

        return response()->json(['message' => 'Link di reset password inviato.']);
    }

    public function resetPassword(ResetPasswordRequest $request, ResetPasswordAction $action): JsonResponse
    {
        $action->handle($request->validated());

        return response()->json(['message' => 'Password aggiornata con successo.']);
    }
}
