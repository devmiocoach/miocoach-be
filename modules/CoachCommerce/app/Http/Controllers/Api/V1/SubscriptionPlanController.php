<?php

namespace Modules\CoachCommerce\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CoachCommerce\Http\Requests\StoreSubscriptionPlanRequest;
use Modules\CoachCommerce\Http\Requests\UpdateSubscriptionPlanRequest;
use Modules\CoachCommerce\Http\Resources\SubscriptionPlanResource;
use Modules\CoachCommerce\Models\SubscriptionPlan;

class SubscriptionPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach) {
            return response()->json(['message' => 'Profilo coach non trovato.'], 404);
        }

        $plans = $coach->subscriptionPlans()->orderBy('created_at', 'desc')->get();

        return response()->json(['data' => SubscriptionPlanResource::collection($plans)]);
    }

    public function store(StoreSubscriptionPlanRequest $request): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach) {
            return response()->json(['message' => 'Profilo coach non trovato.'], 404);
        }

        $plan = $coach->subscriptionPlans()->create($request->validated());

        return response()->json(['data' => new SubscriptionPlanResource($plan)], 201);
    }

    public function update(UpdateSubscriptionPlanRequest $request, SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach || $subscriptionPlan->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $subscriptionPlan->update($request->validated());

        return response()->json(['data' => new SubscriptionPlanResource($subscriptionPlan->fresh())]);
    }

    public function destroy(Request $request, SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach || $subscriptionPlan->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $subscriptionPlan->delete();

        return response()->json(null, 204);
    }
}
