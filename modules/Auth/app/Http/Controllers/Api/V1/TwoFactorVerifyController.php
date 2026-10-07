<?php

namespace Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Auth\Actions\VerifyTwoFactorLoginAction;
use Modules\Auth\Http\Requests\TwoFactorVerifyRequest;

class TwoFactorVerifyController extends Controller
{
    use ApiResponse;

    public function __invoke(TwoFactorVerifyRequest $request, VerifyTwoFactorLoginAction $action): JsonResponse
    {
        $result = $action->handle(
            tempToken: $request->temp_token,
            code: $request->code,
            deviceName: $request->device_name,
        );

        return $this->tokenResponse($result['user'], $result['access_token'], $result['refresh_token']);
    }
}
