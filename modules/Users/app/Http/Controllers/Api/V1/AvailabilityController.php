<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Actions\ReplaceAvailabilityAction;
use App\Modules\Users\Http\Requests\UpdateAvailabilityRequest;
use App\Modules\Users\Http\Resources\CoachAvailabilityResource;
use Illuminate\Http\JsonResponse;

class AvailabilityController extends Controller
{
    public function replace(UpdateAvailabilityRequest $request, ReplaceAvailabilityAction $action): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach) {
            return response()->json(['message' => 'Profilo coach non trovato.'], 404);
        }

        $slots = $action->handle($coach, $request->validated()['slots']);

        return response()->json(['data' => CoachAvailabilityResource::collection($slots)]);
    }
}
