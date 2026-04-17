<?php

namespace App\Modules\WorkoutPlans\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\WorkoutPlans\Actions\CreateWorkoutPlanAction;
use App\Modules\WorkoutPlans\Actions\DeleteWorkoutPlanAction;
use App\Modules\WorkoutPlans\Actions\UpdateWorkoutPlanAction;
use App\Modules\WorkoutPlans\Http\Requests\WorkoutPlanRequest;
use App\Modules\WorkoutPlans\Http\Resources\WorkoutPlanResource;
use App\Modules\WorkoutPlans\Models\WorkoutPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkoutPlanController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $coachId = $request->user()->coach?->id ?? $request->user()->id;

        $plans = WorkoutPlan::where('coach_id', $coachId)
            ->withCount('exercises')
            ->latest()
            ->paginate(20);

        return WorkoutPlanResource::collection($plans);
    }

    public function store(WorkoutPlanRequest $request, CreateWorkoutPlanAction $action): WorkoutPlanResource
    {
        $coachId = $request->user()->id;
        $plan = $action->execute($request->validated(), $coachId);

        return new WorkoutPlanResource($plan);
    }

    public function show(WorkoutPlan $workoutPlan): WorkoutPlanResource
    {
        $this->authorize('view', $workoutPlan);

        return new WorkoutPlanResource($workoutPlan->load('exercises'));
    }

    public function update(WorkoutPlanRequest $request, WorkoutPlan $workoutPlan, UpdateWorkoutPlanAction $action): WorkoutPlanResource
    {
        $this->authorize('update', $workoutPlan);

        $plan = $action->execute($workoutPlan, $request->validated());

        return new WorkoutPlanResource($plan);
    }

    public function destroy(WorkoutPlan $workoutPlan, DeleteWorkoutPlanAction $action): JsonResponse
    {
        $this->authorize('delete', $workoutPlan);

        $action->execute($workoutPlan);

        return response()->json(['message' => 'Workout plan deleted.']);
    }
}
