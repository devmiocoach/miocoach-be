<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(array $data = [], int $status = 200): JsonResponse
    {
        return response()->json($data, $status);
    }

    protected function message(string $code, string $message, int $status = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'code' => $code,
            'message' => $message,
        ], $status);
    }

    protected function tokenResponse(User $user, string $accessToken, string $refreshToken): JsonResponse
    {
        return response()
            ->json([
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => config('auth.modules.jwt.access_ttl'),
                'user' => $this->userPayload($user),
            ])
            ->withCookie($this->refreshTokenCookie($refreshToken));
    }

    protected function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roles->first()?->name,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
        ];
    }

    private function refreshTokenCookie(string $refreshToken)
    {
        return cookie(
            name: 'refresh_token',
            value: $refreshToken,
            minutes: 60 * 24 * 30,
            path: '/api/v1/auth',
            secure: true,
            httpOnly: true,
            sameSite: 'Strict',
        );
    }
}
