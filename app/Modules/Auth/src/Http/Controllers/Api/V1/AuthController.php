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
use App\Modules\Auth\Services\TokenBlacklistService;
use App\Modules\Auth\Http\Requests\RegisterCoachRequest;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginAction $action): JsonResponse
    {
        $result = $action->handle(
            email:      $request->email,
            password:   $request->password,
            deviceName: $request->device_name,
        );

        if (isset($result['two_factor_required'])) {
            return response()->json([
                'two_factor_required' => true,
                'temp_token'          => $result['temp_token'],
            ]);
        }

        return $this->tokenResponse($result['access_token'], $result['refresh_token']);
    }

    public function registerClient(RegisterRequest $request, RegisterAction $action): JsonResponse
    {
        $action->handle(data: $request->validated(), role: 'client');

        return response()->json(['message' => 'Controlla la tua email per verificare il tuo account.'], 201);
    }

    public function registerCoach(RegisterCoachRequest $request, RegisterCoachAction $action): JsonResponse
    {
        $action->handle(data: $request->validated());

        return response()->json(['message' => 'Controlla la tua email per verificare il tuo account.'], 201);
    }

    public function logout(Request $request, LogoutAction $action): JsonResponse
    {
        $refreshToken = $request->cookie('refresh_token');
        $action->handle($request->user(), $refreshToken, $request->bearerToken());

        return response()
            ->json(['message' => 'Logout effettuato con successo.'])
            ->withoutCookie('refresh_token');
    }

    public function refresh(Request $request, RefreshTokenAction $action): JsonResponse
    {
        $refreshToken = $request->cookie('refresh_token');
        $result       = $action->handle($request->user(), $refreshToken, $request->input('device_name'));

        return $this->tokenResponse($result['access_token'], $result['refresh_token']);
    }

    public function logoutAll(Request $request, RevokeAllTokensAction $action, TokenBlacklistService $blacklist): JsonResponse
    {
        $action->handle($request->user());

        // Blacklist the current access token so it's immediately invalid
        if ($token = $request->bearerToken()) {
            $blacklist->add($token);
        }

        return response()
            ->json(['message' => 'Tutti i token sono stati revocati.'])
            ->withoutCookie('refresh_token');
    }

    private function tokenResponse(string $accessToken, string $refreshToken): JsonResponse
    {
        return response()
            ->json([
                'access_token' => $accessToken,
                'token_type'   => 'Bearer',
                'expires_in'   => config('auth.jwt.access_ttl'),
            ])
            ->withCookie(cookie(
                name:     'refresh_token',
                value:    $refreshToken,
                minutes:  60 * 24 * 30,
                path:     '/api/v1/auth',
                secure:   true,
                httpOnly: true,
                sameSite: 'Strict',
            ));
    }
}
