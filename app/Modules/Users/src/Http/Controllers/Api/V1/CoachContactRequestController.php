<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Actions\SendContactRequestAction;
use App\Modules\Users\Http\Resources\CoachContactRequestResource;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachContactRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoachContactRequestController extends Controller
{
    public function store(Request $request, Coach $coach, SendContactRequestAction $action): JsonResponse
    {
        $request->validate([
            'message' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $client = $request->user()->client;

        if (! $client) {
            return response()->json(['message' => 'Profilo cliente non trovato.'], 404);
        }

        $contactRequest = $action->handle($coach, $client, $request->input('message'));

        return response()->json(['data' => new CoachContactRequestResource($contactRequest)], 201);
    }

    public function index(Request $request): JsonResponse
    {
        // Implemented in Task 5
        return response()->json(['data' => []]);
    }

    public function update(Request $request, CoachContactRequest $contactRequest): JsonResponse
    {
        // Implemented in Task 5
        return response()->json(['data' => []]);
    }
}
