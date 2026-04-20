<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Actions\PublishCoachProfileAction;
use App\Modules\Users\Actions\UpdateCoachProfileAction;
use App\Modules\Users\Http\Requests\UpdateCoachProfileRequest;
use App\Modules\Users\Http\Resources\ClientResource;
use App\Modules\Users\Http\Resources\CoachResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoachController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach) {
            return response()->json(['message' => 'Profilo coach non trovato.'], 404);
        }

        return response()->json(['data' => new CoachResource($coach->load('user'))]);
    }

    public function publish(Request $request, PublishCoachProfileAction $action): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach) {
            return response()->json(['message' => 'Profilo coach non trovato.'], 404);
        }

        $result = $action->handle($coach);

        if (! $result['ok']) {
            return response()->json([
                'message' => 'Il profilo non soddisfa i requisiti minimi per la pubblicazione.',
                'errors'  => ['missing_fields' => $result['missing']],
            ], 422);
        }

        return response()->json(['data' => new CoachResource($result['coach']->load('user'))]);
    }

    public function update(UpdateCoachProfileRequest $request, UpdateCoachProfileAction $action): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach) {
            return response()->json(['message' => 'Profilo coach non trovato.'], 404);
        }

        $coach = $action->handle($coach, $request->validated());

        return response()->json(['data' => new CoachResource($coach->load('user'))]);
    }

    public function clients(Request $request): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach) {
            return response()->json(['message' => 'Profilo coach non trovato.'], 404);
        }

        $clients = $coach->clients()
            ->with('user')
            ->latest('joined_at')
            ->paginate(20);

        return response()->json(ClientResource::collection($clients)->response()->getData(true));
    }
}
