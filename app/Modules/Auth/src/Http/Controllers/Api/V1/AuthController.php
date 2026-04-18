<?php

namespace App\Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\LoginAction;
use App\Modules\Auth\Actions\LogoutAction;
use App\Modules\Auth\Actions\RefreshTokenAction;
use App\Modules\Auth\Actions\RegisterAction;
use App\Modules\Auth\Actions\RegisterCoachAction;
use App\Modules\Auth\Actions\RevokeAllTokensAction;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Requests\RegisterCoachRequest;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use App\Modules\Auth\Http\Resources\AuthTokenResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginAction $action): JsonResponse
    {
        $result = $action->handle(
            email: $request->email,
            password: $request->password,
            deviceType: $request->device_type ?? 'web',
            deviceName: $request->device_name,
        );

        if (isset($result['two_factor_required'])) {
            return response()->json(['two_factor_required' => true, 'email' => $result['email']]);
        }

        return response()->json(new AuthTokenResource($result));
    }

    public function registerClient(RegisterRequest $request, RegisterAction $action): JsonResponse
    {
        $result = $action->handle(
            data: $request->validated(),
            role: 'client',
            deviceType: $request->device_type ?? 'web',
            deviceName: $request->device_name,
        );

        return response()->json(new AuthTokenResource($result), 201);
    }

    public function registerCoach(RegisterCoachRequest $request, RegisterCoachAction $action): JsonResponse
    {
        $result = $action->handle(
            data: $request->validated(),
            deviceType: $request->device_type ?? 'web',
            deviceName: $request->device_name,
        );

        return response()->json(new AuthTokenResource($result), 201);
    }

    public function logout(Request $request, LogoutAction $action): JsonResponse
    {
        $action->handle($request->user(), $request->device_type ?? 'web');

        return response()->json(['message' => 'Logout effettuato con successo.']);
    }

    public function refresh(Request $request, RefreshTokenAction $action): JsonResponse
    {
        $currentToken = $request->bearerToken();
        $result = $action->handle($request->user(), $currentToken, $request->input('device_name'));

        return response()->json(new AuthTokenResource($result));
    }

    public function logoutAll(Request $request, RevokeAllTokensAction $action): JsonResponse
    {
        $action->handle($request->user());

        return response()->json(['message' => 'Tutti i token sono stati revocati.']);
    }
}
