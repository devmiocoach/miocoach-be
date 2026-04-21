<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Actions\AddClientTagAction;
use App\Modules\Users\Actions\RemoveClientTagAction;
use App\Modules\Users\Http\Resources\ClientResource;
use App\Modules\Users\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientTagController extends Controller
{
    public function store(Request $request, Client $client, AddClientTagAction $action): JsonResponse
    {
        $request->validate(['tag' => ['required', 'string', 'max:50']]);

        $coach = $request->user()->coach;
        if (! $coach || $client->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $client = $action->handle($client, $request->input('tag'));

        return response()->json(['data' => new ClientResource($client->load('user'))]);
    }

    public function destroy(Request $request, Client $client, string $tag, RemoveClientTagAction $action): JsonResponse
    {
        $coach = $request->user()->coach;
        if (! $coach || $client->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $action->handle($client, $tag);

        return response()->json(null, 204);
    }
}
