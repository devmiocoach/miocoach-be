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
            'activePackages',
            'availabilities',
        ]);

        $coach->loadCount('publishedReviews as reviews_count');
        $coach->loadAvg('publishedReviews as avg_rating', 'rating');

        return response()->json(['data' => new PublicCoachResource($coach)]);
    }
}
