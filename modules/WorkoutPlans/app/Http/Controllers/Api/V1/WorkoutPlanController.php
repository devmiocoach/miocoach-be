<?php

namespace Modules\WorkoutPlans\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\WorkoutPlans\Data\PlanTreeData;
use Modules\WorkoutPlans\Http\Requests\AssignWorkoutPlanRequest;
use Modules\WorkoutPlans\Http\Requests\StoreWorkoutPlanRequest;
use Modules\WorkoutPlans\Http\Requests\UpdateWorkoutPlanRequest;
use Modules\WorkoutPlans\Http\Resources\WorkoutPlanDetailResource;
use Modules\WorkoutPlans\Http\Resources\WorkoutPlanResource;
use Modules\WorkoutPlans\Models\PlanAssignment;
use Modules\WorkoutPlans\Models\WorkoutPlan;
use Modules\WorkoutPlans\Services\WorkoutPlanWriter;

class WorkoutPlanController extends Controller
{
    public function __construct(private WorkoutPlanWriter $writer) {}

    public function index(Request $request): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach) {
            return response()->json(['message' => 'Coach profile not found.'], 403);
        }

        $plans = WorkoutPlan::forCoach($coach->id)
            ->active()
            ->latest()
            ->get();

        return response()->json(['data' => WorkoutPlanResource::collection($plans)]);
    }

    public function store(StoreWorkoutPlanRequest $request): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach) {
            return response()->json(['message' => 'Coach profile not found.'], 403);
        }

        $plan = $this->writer->write($coach, null, PlanTreeData::fromArray($request->validated()));

        return response()->json(['data' => new WorkoutPlanDetailResource($plan)], 201);
    }

    public function show(Request $request, WorkoutPlan $workoutPlan): JsonResponse
    {
        $user = $request->user();

        if ($user->cannot('view', $workoutPlan)) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $workoutPlan->load(['workoutDays.workoutExercises.exercise']);

        return response()->json(['data' => new WorkoutPlanDetailResource($workoutPlan)]);
    }

    public function update(UpdateWorkoutPlanRequest $request, WorkoutPlan $workoutPlan): JsonResponse
    {
        if ($request->user()->cannot('update', $workoutPlan)) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        if ($request->has('days')) {
            $plan = $this->writer->write($request->user()->coach, $workoutPlan, PlanTreeData::fromArray($request->validated()));

            return response()->json(['data' => new WorkoutPlanDetailResource($plan)]);
        }

        $workoutPlan->update(array_filter([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'goal' => $request->validated('goal'),
            'difficulty' => $request->validated('difficulty'),
            'duration_weeks' => $request->validated('durationWeeks'),
            'sessions_per_week' => $request->validated('sessionsPerWeek'),
            'is_template' => $request->has('isTemplate') ? $request->boolean('isTemplate') : null,
            'is_active' => $request->has('isActive') ? $request->boolean('isActive') : null,
        ], fn ($v) => $v !== null));

        return response()->json(['data' => new WorkoutPlanDetailResource(
            $workoutPlan->fresh()->load(['workoutDays.workoutExercises.exercise'])
        )]);
    }

    public function destroy(Request $request, WorkoutPlan $workoutPlan): JsonResponse
    {
        if ($request->user()->cannot('delete', $workoutPlan)) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $workoutPlan->delete();

        return response()->json(null, 204);
    }

    public function duplicate(Request $request, WorkoutPlan $workoutPlan): JsonResponse
    {
        if ($request->user()->cannot('duplicate', $workoutPlan)) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $copy = DB::transaction(function () use ($workoutPlan) {
            $copy = $workoutPlan->replicate();
            $copy->name = $workoutPlan->name.' (copia)';
            $copy->save();

            foreach ($workoutPlan->workoutDays()->with('workoutExercises')->get() as $day) {
                $newDay = $day->replicate(['workout_plan_id']);
                $newDay->workout_plan_id = $copy->id;
                $newDay->save();

                foreach ($day->workoutExercises as $exercise) {
                    $newExercise = $exercise->replicate(['workout_plan_id', 'workout_day_id']);
                    $newExercise->workout_plan_id = $copy->id;
                    $newExercise->workout_day_id = $newDay->id;
                    $newExercise->save();
                }
            }

            return $copy->load(['workoutDays.workoutExercises.exercise']);
        });

        return response()->json(['data' => new WorkoutPlanDetailResource($copy)], 201);
    }

    public function assign(AssignWorkoutPlanRequest $request, WorkoutPlan $workoutPlan): JsonResponse
    {
        if ($request->user()->cannot('update', $workoutPlan)) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $activeFrom = $request->validated('activeFrom')
            ? Carbon::parse($request->validated('activeFrom'))
            : null;

        foreach ($request->validated('clientIds') as $clientId) {
            PlanAssignment::updateOrCreate(
                ['workout_plan_id' => $workoutPlan->id, 'client_id' => $clientId],
                ['active_from' => $activeFrom, 'assigned_at' => now()]
            );
        }

        return response()->json(['message' => 'Piano assegnato con successo.', 'assigned' => count($request->validated('clientIds'))]);
    }
}
