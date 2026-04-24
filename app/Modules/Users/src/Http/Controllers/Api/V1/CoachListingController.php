<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Http\Resources\CoachListingResource;
use App\Modules\Users\Models\Coach;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoachListingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Coach::query()
            ->where('is_published', true)
            ->with('user');

        if ($request->filled('search')) {
            $term = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($term) {
                $q->whereHas('user', fn ($u) => $u->where('name', 'LIKE', $term))
                  ->orWhere('tagline', 'LIKE', $term)
                  ->orWhere('bio', 'LIKE', $term);
            });
        }

        if ($request->filled('city')) {
            $query->where('city', 'LIKE', '%' . $request->input('city') . '%');
        }

        if ($request->filled('mode')) {
            $query->where('mode', $request->input('mode'));
        }

        if ($request->filled('price_max')) {
            $query->where('price_per_session', '<=', (float) $request->input('price_max'));
        }

        if ($request->filled('specialization')) {
            foreach ((array) $request->input('specialization') as $spec) {
                $query->whereJsonContains('specializations', $spec);
            }
        }

        if ($request->filled('languages')) {
            foreach ((array) $request->input('languages') as $lang) {
                $query->whereJsonContains('languages', $lang);
            }
        }

        $limit   = max(1, min((int) $request->input('limit', 20), 50));
        $coaches = $query->latest('id')->paginate($limit);

        return response()->json(CoachListingResource::collection($coaches)->response()->getData(true));
    }
}
