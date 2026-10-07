<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Http\Requests\StoreCoachPackageRequest;
use App\Modules\Users\Http\Requests\UpdateCoachPackageRequest;
use App\Modules\Users\Http\Resources\CoachPackageResource;
use App\Modules\Users\Models\CoachPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoachPackageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach) {
            return response()->json(['message' => 'Profilo coach non trovato.'], 404);
        }

        $packages = $coach->packages()->get();

        return response()->json(['data' => CoachPackageResource::collection($packages)]);
    }

    public function store(StoreCoachPackageRequest $request): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach) {
            return response()->json(['message' => 'Profilo coach non trovato.'], 404);
        }

        $package = $coach->packages()->create($request->validated());

        return response()->json(['data' => new CoachPackageResource($package)], 201);
    }

    public function update(UpdateCoachPackageRequest $request, CoachPackage $package): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach || $package->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $package->update($request->validated());

        return response()->json(['data' => new CoachPackageResource($package->fresh())]);
    }

    public function destroy(Request $request, CoachPackage $package): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach || $package->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $package->delete();

        return response()->json(null, 204);
    }
}
