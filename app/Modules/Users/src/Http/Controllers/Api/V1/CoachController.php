<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Actions\CreateClientAction;
use App\Modules\Users\Actions\PublishCoachProfileAction;
use App\Modules\Users\Actions\UpdateCoachProfileAction;
use App\Modules\Users\Http\Requests\CreateClientRequest;
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

        $query = $coach->clients()->with('user');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('tags')) {
            foreach ((array) $request->input('tags') as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        if ($request->filled('expiresWithin')) {
            $days = (int) $request->input('expiresWithin');
            $query->whereNotNull('subscription_expires_at')
                  ->where('subscription_expires_at', '<=', now()->addDays($days));
        }

        if ($request->filled('search')) {
            $term = '%' . $request->input('search') . '%';
            $query->whereHas('user', fn ($q) => $q->where('name', 'LIKE', $term)
                                                   ->orWhere('email', 'LIKE', $term));
        }

        $query->orderByRaw('subscription_expires_at IS NULL, subscription_expires_at ASC');

        $limit = max(1, min((int) $request->input('limit', 20), 100));
        $clients = $query->paginate($limit);

        return response()->json(ClientResource::collection($clients)->response()->getData(true));
    }

    public function store(CreateClientRequest $request, CreateClientAction $action): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach) {
            return response()->json(['message' => 'Profilo coach non trovato.'], 404);
        }

        $result = $action->handle($coach, $request->validated());

        if ($result['status'] === 'invited') {
            return response()->json([
                'message' => "Invito inviato. Il cliente riceverà un'email per completare la registrazione.",
            ], 202);
        }

        return response()->json(['data' => new ClientResource($result['client'])], 201);
    }
}
