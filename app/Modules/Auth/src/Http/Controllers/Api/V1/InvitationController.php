<?php

namespace App\Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\RegisterViaInviteAction;
use App\Modules\Auth\Http\Requests\RegisterViaInviteRequest;
use App\Modules\Auth\Http\Resources\AuthTokenResource;
use App\Modules\Users\Models\CoachInvitation;
use Illuminate\Http\JsonResponse;

class InvitationController extends Controller
{
    public function validate(string $token): JsonResponse
    {
        $invitation = CoachInvitation::with('coach.user')->where('token', $token)->first();

        if (! $invitation || ! $invitation->isPending()) {
            return response()->json(['valid' => false, 'message' => 'Il link di invito non è valido o è scaduto.'], 422);
        }

        return response()->json([
            'valid'      => true,
            'email'      => $invitation->email,
            'coach_name' => $invitation->coach->user->name,
            'expires_at' => $invitation->expires_at->toISOString(),
        ]);
    }

    public function register(string $token, RegisterViaInviteRequest $request, RegisterViaInviteAction $action): JsonResponse
    {
        $result = $action->handle(
            token: $token,
            data: $request->validated(),
            deviceType: $request->device_type ?? 'web',
            deviceName: $request->device_name,
        );

        return response()->json(new AuthTokenResource($result), 201);
    }
}
