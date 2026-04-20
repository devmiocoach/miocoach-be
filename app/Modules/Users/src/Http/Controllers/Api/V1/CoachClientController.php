<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Http\Resources\ClientDetailResource;
use App\Modules\Users\Services\EncryptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoachClientController extends Controller
{
    public function show(Request $request, Client $client, EncryptionService $encryption): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach || $client->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $client->load(['user', 'notes', 'files']);

        $decryptedAnamnesis = null;
        $anamnesis = $client->anamnesis;
        if ($anamnesis) {
            try {
                $decryptedAnamnesis = $encryption->decrypt(
                    $anamnesis->content_encrypted,
                    $anamnesis->iv,
                    $anamnesis->tag
                );
            } catch (\RuntimeException) {
                $decryptedAnamnesis = null;
            }
        }

        return response()->json(['data' => new ClientDetailResource($client, $decryptedAnamnesis)]);
    }
}
