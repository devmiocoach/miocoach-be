<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Actions\AddClientNoteAction;
use App\Modules\Users\Http\Requests\StoreClientNoteRequest;
use App\Modules\Users\Http\Resources\ClientNoteResource;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\ClientNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientNoteController extends Controller
{
    public function index(Request $request, Client $client): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach || ! $coach->athletes()->where('athlete_id', $client->id)->exists()) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $notes = $client->notes()->where('coach_id', $coach->id)->get();

        return response()->json(['data' => ClientNoteResource::collection($notes)]);
    }

    public function store(StoreClientNoteRequest $request, Client $client, AddClientNoteAction $action): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach || ! $coach->athletes()->where('athlete_id', $client->id)->exists()) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $note = $action->handle($client, $coach, $request->validated('content'));

        return response()->json(['data' => new ClientNoteResource($note)], 201);
    }

    public function destroy(Request $request, Client $client, ClientNote $clientNote): JsonResponse
    {
        $coach = $request->user()->coach;

        if (
            ! $coach ||
            ! $coach->athletes()->where('athlete_id', $client->id)->exists() ||
            $clientNote->client_id !== $client->id ||
            $clientNote->coach_id !== $coach->id
        ) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $clientNote->delete();

        return response()->json(null, 204);
    }
}
