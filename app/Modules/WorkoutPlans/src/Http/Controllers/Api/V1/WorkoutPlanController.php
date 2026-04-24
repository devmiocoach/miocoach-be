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
        $user = $request->user();

        // Un client vede solo i piani a lui assegnati; un coach vede i propri piani.
        $plans = WorkoutPlan::when(
            $user->hasRole('client'),
            fn ($q) => $q->where('client_id', $user->client?->id),
            fn ($q) => $q->where('coach_id', $user->coach?->id),
        )
            ->withCount('exercises')
            ->latest()
            ->paginate(20);

        return WorkoutPlanResource::collection($plans);
    }

    public function store(WorkoutPlanRequest $request, CreateWorkoutPlanAction $action): WorkoutPlanResource
    {
        $this->authorize('create', WorkoutPlan::class);

        // Usa il coach_id (FK nella tabella coaches) e non lo user_id:
        // workout_plans.coach_id referenzia coaches.id.
        $coachId = $request->user()->coach?->id;

        if (! $coachId) {
            abort(403, 'Profilo coach non trovato.');
        }

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
