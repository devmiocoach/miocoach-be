<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Actions\UpdateClientAnamnesisAction;
use App\Modules\Users\Http\Requests\UpdateClientAnamnesisRequest;
use App\Modules\Users\Models\Client;
use Illuminate\Http\JsonResponse;

class ClientAnamnesisController extends Controller
{
    public function update(UpdateClientAnamnesisRequest $request, Client $client, UpdateClientAnamnesisAction $action): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach || $client->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $action->handle($client, $coach, $request->validated('content'));

        return response()->json(['data' => ['message' => 'Anamnesi aggiornata.']]);
    }
}
