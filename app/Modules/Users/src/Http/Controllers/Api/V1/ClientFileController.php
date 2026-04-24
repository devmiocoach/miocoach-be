<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Actions\StoreClientFileAction;
use App\Modules\Users\Http\Requests\StoreClientFileRequest;
use App\Modules\Users\Http\Resources\ClientFileResource;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\ClientFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClientFileController extends Controller
{
    public function store(StoreClientFileRequest $request, Client $client, StoreClientFileAction $action): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach || $client->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $record = $action->handle(
            $client,
            $coach,
            $request->file('file'),
            $request->input('name')
        );

        return response()->json(['data' => new ClientFileResource($record)], 201);
    }

    public function download(Request $request, Client $client, ClientFile $clientFile): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach || $client->coach_id !== $coach->id || $clientFile->client_id !== $client->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $expiresAt = now()->addMinutes(15);
        $signedUrl = Storage::disk('r2_docs')->temporaryUrl($clientFile->storage_key, $expiresAt);

        return response()->json([
            'data' => [
                'url'        => $signedUrl,
                'expires_at' => $expiresAt->toISOString(),
            ],
        ]);
    }
}
