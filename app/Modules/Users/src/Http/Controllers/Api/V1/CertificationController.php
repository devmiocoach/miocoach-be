<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Actions\DeleteCertificationAction;
use App\Modules\Users\Actions\StoreCertificationAction;
use App\Modules\Users\Http\Requests\StoreCertificationRequest;
use App\Modules\Users\Http\Resources\CertificationResource;
use App\Modules\Users\Models\Certification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificationController extends Controller
{
    public function store(StoreCertificationRequest $request, StoreCertificationAction $action): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach) {
            return response()->json(['message' => 'Profilo coach non trovato.'], 404);
        }

        $certification = $action->handle($coach, $request->file('file'), $request->validated());

        return response()->json(['data' => new CertificationResource($certification)], 201);
    }

    public function destroy(Request $request, Certification $certification, DeleteCertificationAction $action): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach || $certification->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $action->handle($certification);

        return response()->json(null, 204);
    }
}
