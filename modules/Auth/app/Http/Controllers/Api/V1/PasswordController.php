<?php

namespace Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Auth\Actions\ForgotPasswordAction;
use Modules\Auth\Actions\ResetPasswordAction;
use Modules\Auth\Http\Requests\ResetPasswordRequest;

class PasswordController extends Controller
{
    use ApiResponse;

    public function forgotPassword(Request $request, ForgotPasswordAction $action): JsonResponse
    {
        $request->validate(['email' => ['required', 'email:rfc,dns', 'max:255']]);

        $action->handle($request->email);

        return $this->message('PASSWORD_RESET_SENT', 'Link di reset password inviato.');
    }

    public function resetPassword(ResetPasswordRequest $request, ResetPasswordAction $action): JsonResponse
    {
        $action->handle($request->validated());

        return $this->message('PASSWORD_RESET_SUCCESS', 'Password aggiornata con successo.');
    }
}
