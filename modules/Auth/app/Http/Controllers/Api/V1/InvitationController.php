<?php

namespace Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Users\Models\CoachInvitation;
use Illuminate\Http\JsonResponse;
use Modules\Auth\Actions\RegisterViaInviteAction;
use Modules\Auth\Http\Requests\RegisterViaInviteRequest;
use Modules\Auth\Services\JwtService;
use Modules\Auth\Services\RefreshTokenService;

class InvitationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly JwtService $jwt,
        private readonly RefreshTokenService $refreshTokens,
    ) {}

    public function validate(string $token): JsonResponse
    {
        $invitation = CoachInvitation::with('coach.user')->where('token', $token)->first();

        if (! $invitation || ! $invitation->isPending() || ! $invitation->coach) {
            return response()->json(['valid' => false, 'message' => 'Il link di invito non è valido o è scaduto.'], 422);
        }

        return response()->json([
            'valid' => true,
            'email' => $invitation->email,
            'coach_name' => $invitation->coach->user->name,
            'expires_at' => $invitation->expires_at->toISOString(),
        ]);
    }

    public function register(string $token, RegisterViaInviteRequest $request, RegisterViaInviteAction $action): JsonResponse
    {
        $user = $action->handle(
            token: $token,
            data: $request->validated(),
        );

        $accessToken = $this->jwt->generateAccessToken($user);
        $refreshToken = $this->refreshTokens->create($user, $request->input('device_name', 'unknown'));

        return $this->tokenResponse($user, $accessToken, $refreshToken);
    }
}
