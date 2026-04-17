<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Actions\UpdateProfileAction;
use App\Modules\Users\Http\Requests\UpdateProfileRequest;
use App\Modules\Users\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['coach', 'client']);

        return response()->json(['data' => new UserResource($user)]);
    }

    public function update(UpdateProfileRequest $request, UpdateProfileAction $action): JsonResponse
    {
        $user = $action->handle($request->user(), $request->validated());

        return response()->json(['data' => new UserResource($user->load(['coach', 'client']))]);
    }

    public function destroy(Request $request): JsonResponse
    {
        // GDPR art. 17 — soft delete immediato
        $request->user()->tokens()->delete();
        $request->user()->delete();

        return response()->json(['message' => 'Account eliminato. Hard delete schedulato entro 30 giorni.']);
    }
}
