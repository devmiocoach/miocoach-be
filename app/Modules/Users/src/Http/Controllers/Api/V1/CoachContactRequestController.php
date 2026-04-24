<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Actions\AcceptContactRequestAction;
use App\Modules\Users\Actions\DeclineContactRequestAction;
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
        $coach = $request->user()->coach;

        if (! $coach) {
            return response()->json(['message' => 'Profilo coach non trovato.'], 404);
        }

        $status = $request->input('status', 'pending');

        $requests = $coach->contactRequests()
            ->with('client.user')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20);

        return response()->json(CoachContactRequestResource::collection($requests)->response()->getData(true));
    }

    public function update(
        Request $request,
        CoachContactRequest $contactRequest,
        AcceptContactRequestAction $accept,
        DeclineContactRequestAction $decline,
    ): JsonResponse {
        $request->validate([
            'action' => ['required', 'in:accept,decline'],
        ]);

        $coach = $request->user()->coach;

        if ($contactRequest->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $updated = $request->input('action') === 'accept'
            ? $accept->handle($contactRequest)
            : $decline->handle($contactRequest);

        return response()->json(['data' => new CoachContactRequestResource($updated->load('client.user'))]);
    }
}
