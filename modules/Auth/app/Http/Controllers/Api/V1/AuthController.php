<?php

namespace Modules\Auth\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Auth\Actions\LoginAction;
use Modules\Auth\Actions\LogoutAction;
use Modules\Auth\Actions\RefreshTokenAction;
use Modules\Auth\Actions\RegisterAction;
use Modules\Auth\Actions\RegisterCoachAction;
use Modules\Auth\Actions\RevokeAllTokensAction;
use Modules\Auth\Http\Requests\ApiLoginRequest;
use Modules\Auth\Http\Requests\ApiRegisterRequest;
use Modules\Auth\Http\Requests\RegisterCoachRequest;
use Modules\Auth\Services\TokenBlacklistService;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(ApiLoginRequest $request, LoginAction $action): JsonResponse
    {
        $result = $action->handle(
            email: $request->email,
            password: $request->password,
            deviceName: $request->device_name,
        );

        if (isset($result['two_factor_required'])) {
            return response()->json([
                'two_factor_required' => true,
                'temp_token' => $result['temp_token'],
            ]);
        }

        return $this->tokenResponse($result['user'], $result['access_token'], $result['refresh_token']);
    }

    public function registerClient(ApiRegisterRequest $request, RegisterAction $action): JsonResponse
    {
        $action->handle(data: $request->validated(), role: Role::CLIENT);

        return $this->message('REGISTRATION_PENDING_VERIFICATION', 'Controlla la tua email per verificare il tuo account.', 201);
    }

    public function registerCoach(RegisterCoachRequest $request, RegisterCoachAction $action): JsonResponse
    {
        $action->handle(data: $request->validated());

        return $this->message('REGISTRATION_PENDING_VERIFICATION', 'Controlla la tua email per verificare il tuo account.', 201);
    }

    public function logout(Request $request, LogoutAction $action): JsonResponse
    {
        $refreshToken = $request->cookie('refresh_token');
        $action->handle($request->user(), $refreshToken, $request->bearerToken());

        return $this->message('LOGOUT_SUCCESS', 'Logout effettuato con successo.')
            ->withoutCookie('refresh_token');
    }

    public function refresh(Request $request, RefreshTokenAction $action): JsonResponse
    {
        // Accept the refresh token from the request body (mobile/API clients)
        // or the httpOnly cookie (web). The user is resolved from the token,
        // so no valid access token is required to refresh.
        $refreshToken = $request->input('refresh_token') ?? $request->cookie('refresh_token');
        $result = $action->handle($refreshToken, $request->input('device_name'));

        return $this->tokenResponse($result['user'], $result['access_token'], $result['refresh_token']);
    }

    public function logoutAll(Request $request, RevokeAllTokensAction $action, TokenBlacklistService $blacklist): JsonResponse
    {
        $action->handle($request->user());

        if ($token = $request->bearerToken()) {
            $blacklist->add($token);
        }

        return $this->message('ALL_TOKENS_REVOKED', 'Tutti i token sono stati revocati.')
            ->withoutCookie('refresh_token');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success($this->userPayload($request->user()));
    }
}
