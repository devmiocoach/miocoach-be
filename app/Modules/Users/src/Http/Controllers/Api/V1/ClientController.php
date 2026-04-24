<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Actions\UpdateClientProfileAction;
use App\Modules\Users\Http\Requests\UpdateClientProfileRequest;
use App\Modules\Users\Http\Resources\ClientResource;
use Illuminate\Http\JsonResponse;

class ClientController extends Controller
{
    public function update(UpdateClientProfileRequest $request, UpdateClientProfileAction $action): JsonResponse
    {
        $client = $request->user()->client;

        if (! $client) {
            return response()->json(['message' => 'Profilo client non trovato.'], 404);
        }

        $client = $action->handle($client, $request->validated());

        return response()->json(['data' => new ClientResource($client->load(['user', 'coach.user']))]);
    }
}
