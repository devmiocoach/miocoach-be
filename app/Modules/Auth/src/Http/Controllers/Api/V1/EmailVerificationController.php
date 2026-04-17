<?php

namespace App\Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Auth\Actions\VerifyEmailAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function verify(Request $request, int $id, string $hash, VerifyEmailAction $action): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw new AuthorizationException();
        }

        $wasVerified = $action->handle($user);

        return response()->json([
            'message' => $wasVerified
                ? 'Email verificata con successo.'
                : 'Email già verificata.',
        ]);
    }

    public function resend(Request $request, VerifyEmailAction $action): JsonResponse
    {
        $action->resend($request->user());

        return response()->json(['message' => 'Email di verifica reinviata.']);
    }
}
