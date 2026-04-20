<?php

namespace App\Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\VerifyTwoFactorLoginAction;
use App\Modules\Auth\Http\Requests\TwoFactorVerifyRequest;
use Illuminate\Http\JsonResponse;

class TwoFactorVerifyController extends Controller
{
    public function __invoke(TwoFactorVerifyRequest $request, VerifyTwoFactorLoginAction $action): JsonResponse
    {
        $result = $action->handle(
            tempToken:  $request->temp_token,
            code:       $request->code,
            deviceName: $request->device_name,
        );

        return response()
            ->json(['access_token' => $result['access_token'], 'token_type' => 'Bearer'])
            ->withCookie(cookie(
                name:     'refresh_token',
                value:    $result['refresh_token'],
                minutes:  60 * 24 * 30,
                path:     '/api/v1/auth',
                secure:   true,
                httpOnly: true,
                sameSite: 'Strict',
            ));
    }
}
