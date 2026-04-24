<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Http\Resources\PublicCoachResource;
use App\Modules\Users\Models\Coach;
use Illuminate\Http\JsonResponse;

class PublicCoachController extends Controller
{
    public function show(Coach $coach): JsonResponse
    {
        if (! $coach->is_published) {
            return response()->json(['message' => 'Profilo coach non trovato.'], 404);
        }

        $coach->load([
            'user',
            'verifiedCertifications',
        ]);

        $coach->setRelation(
            'nextSlots',
            $coach->availabilities()->orderBy('day_of_week')->orderBy('start_time')->limit(5)->get()
        );

        return response()->json(['data' => new PublicCoachResource($coach)]);
    }
}
