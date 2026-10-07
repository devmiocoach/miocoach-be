<?php

namespace Modules\WorkoutPlans\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\WorkoutPlans\Http\Resources\PersonalRecordResource;
use Modules\WorkoutPlans\Http\Resources\WorkoutPlanResource;
use Modules\WorkoutPlans\Http\Resources\WorkoutSessionResource;
use Modules\WorkoutPlans\Models\PersonalRecord;
use Modules\WorkoutPlans\Models\PlanAssignment;
use Modules\WorkoutPlans\Models\WorkoutSession;

/**
 * Athlete-facing (client) read endpoints, all scoped to the authenticated
 * client resolved from the JWT — never from a URL parameter.
 */
class ClientWorkoutController extends Controller
{
    public function plans(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);

        if ($client instanceof JsonResponse) {
            return $client;
        }

        $assignments = PlanAssignment::where('client_id', $client->id)
            ->whereHas('workoutPlan', fn ($q) => $q->where('is_active', true))
            ->with('workoutPlan')
            ->latest('assigned_at')
            ->get();

        $data = $assignments->map(fn (PlanAssignment $a) => [
            'activeFrom' => $a->active_from?->toDateString(),
            'assignedAt' => $a->assigned_at?->toISOString(),
            'plan' => new WorkoutPlanResource($a->workoutPlan),
        ]);

        return response()->json(['data' => $data]);
    }

    public function sessions(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);

        if ($client instanceof JsonResponse) {
            return $client;
        }

        $sessions = WorkoutSession::where('client_id', $client->id)
            ->with('workoutDay')
            ->latest('started_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => WorkoutSessionResource::collection($sessions)]);
    }

    public function personalRecords(Request $request): JsonResponse
    {
        $client = $this->resolveClient($request);

        if ($client instanceof JsonResponse) {
            return $client;
        }

        $records = PersonalRecord::where('client_id', $client->id)
            ->with('exercise')
            ->latest('recorded_at')
            ->get();

        return response()->json(['data' => PersonalRecordResource::collection($records)]);
    }

    /**
     * Resolve the authenticated client, or a 403 JSON response when the user
     * has no client profile.
     */
    private function resolveClient(Request $request): Client|JsonResponse
    {
        $client = $request->user()->client;

        if (! $client) {
            return response()->json(['message' => 'Client profile not found.'], 403);
        }

        return $client;
    }
}
