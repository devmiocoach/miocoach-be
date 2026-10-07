<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Actions\UpdateClientAnamnesisAction;
use App\Modules\Users\Http\Requests\UpdateClientAnamnesisRequest;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Services\EncryptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientAnamnesisController extends Controller
{
    public function __construct(private readonly EncryptionService $encryption) {}

    public function show(Request $request, Client $client): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach || ! $coach->athletes()->where('athlete_id', $client->id)->exists()) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $anamnesis = $client->anamnesis;

        if (! $anamnesis) {
            return response()->json(['data' => null]);
        }

        $content = $this->encryption->decrypt(
            $anamnesis->content_encrypted,
            $anamnesis->iv,
            $anamnesis->tag
        );

        return response()->json(['data' => ['content' => $content]]);
    }

    public function update(UpdateClientAnamnesisRequest $request, Client $client, UpdateClientAnamnesisAction $action): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach || ! $coach->athletes()->where('athlete_id', $client->id)->exists()) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $action->handle($client, $coach, $request->validated('content'));

        return response()->json(['data' => ['message' => 'Anamnesi aggiornata.']]);
    }
}
