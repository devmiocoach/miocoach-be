<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Actions\AddClientNoteAction;
use App\Modules\Users\Http\Requests\StoreClientNoteRequest;
use App\Modules\Users\Http\Resources\ClientNoteResource;
use App\Modules\Users\Models\Client;
use Illuminate\Http\JsonResponse;

class ClientNoteController extends Controller
{
    public function store(StoreClientNoteRequest $request, Client $client, AddClientNoteAction $action): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach || $client->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $note = $action->handle($client, $coach, $request->validated('content'));

        return response()->json(['data' => new ClientNoteResource($note)], 201);
    }
}
