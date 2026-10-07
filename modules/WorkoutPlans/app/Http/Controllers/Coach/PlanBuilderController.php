<?php

namespace Modules\WorkoutPlans\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\WorkoutPlans\Data\PlanTreeData;
use Modules\WorkoutPlans\Http\Requests\StoreCoachPlanRequest;
use Modules\WorkoutPlans\Http\Requests\UpdateCoachPlanRequest;
use Modules\WorkoutPlans\Http\Resources\ExerciseResource;
use Modules\WorkoutPlans\Http\Resources\WorkoutPlanDetailResource;
use Modules\WorkoutPlans\Models\Exercise;
use Modules\WorkoutPlans\Models\WorkoutPlan;
use Modules\WorkoutPlans\Services\WorkoutPlanWriter;

class PlanBuilderController extends Controller
{
    public function __construct(private WorkoutPlanWriter $writer) {}

    public function index(Request $request): Response
    {
        $coach = $request->user()->coach;

        $plans = WorkoutPlan::forCoach($coach->id)
            ->withCount('workoutDays')
            ->latest()
            ->get()
            ->map(fn (WorkoutPlan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
                'goal' => $plan->goal?->value,
                'isTemplate' => $plan->is_template,
                'daysCount' => $plan->workout_days_count,
                'updatedAt' => $plan->updated_at?->toISOString(),
            ]);

        return Inertia::render('WorkoutPlans::Coach/Plans/Index', [
            'plans' => $plans,
            'stats' => [
                'plans' => $plans->count(),
                'exercises' => Exercise::active()->forCoach($coach->id)->count(),
            ],
        ])->withoutSSR();
    }

    public function create(Request $request): Response
    {
        $coach = $request->user()->coach;

        return Inertia::render('WorkoutPlans::Coach/Plans/Builder', [
            'plan' => null,
            'exercises' => ExerciseResource::collection(
                Exercise::active()->forCoach($coach->id)->orderBy('name')->get()
            )->toArray($request),
            'clients' => $this->athletes($coach),
        ])->withoutSSR();
    }

    public function edit(Request $request, WorkoutPlan $plan): Response
    {
        Gate::authorize('update', $plan);

        $plan->load(['workoutDays.workoutExercises.exercise']);

        return Inertia::render('WorkoutPlans::Coach/Plans/Builder', [
            // Fully resolve to plain arrays: a bare ->toArray() leaves nested
            // resource collections (e.g. `days`) as Responsable objects, which
            // Inertia re-resolves through toResponse() and wraps in a `data` key —
            // breaking the builder, which expects `days` to be a flat array.
            'plan' => json_decode(
                (new WorkoutPlanDetailResource($plan))->toJson(),
                true
            ),
            'exercises' => ExerciseResource::collection(
                Exercise::active()->forCoach($request->user()->coach->id)->orderBy('name')->get()
            )->toArray($request),
            'clients' => $this->athletes($request->user()->coach),
            'assignedClientIds' => $plan->assignments()->pluck('client_id'),
        ])->withoutSSR();
    }

    public function store(StoreCoachPlanRequest $request): JsonResponse
    {
        $coach = $request->user()->coach;

        $plan = $this->writer->write($coach, null, PlanTreeData::fromArray($request->validated()));
        $this->maybeAssign($request, $plan);

        return response()->json(['plan' => new WorkoutPlanDetailResource($plan)], 201);
    }

    public function update(UpdateCoachPlanRequest $request, WorkoutPlan $plan): JsonResponse
    {
        Gate::authorize('update', $plan);

        $plan = $this->writer->write($request->user()->coach, $plan, PlanTreeData::fromArray($request->validated()));
        $this->maybeAssign($request, $plan);

        return response()->json(['plan' => new WorkoutPlanDetailResource($plan)]);
    }

    public function duplicate(Request $request, WorkoutPlan $plan): RedirectResponse
    {
        Gate::authorize('duplicate', $plan);

        $copy = $plan->replicate();
        $copy->name = $plan->name.' (copia)';
        $copy->save();

        foreach ($plan->workoutDays()->with('workoutExercises')->get() as $day) {
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

        return redirect()->route('coach.plans.edit', $copy);
    }

    public function destroy(Request $request, WorkoutPlan $plan): RedirectResponse
    {
        Gate::authorize('delete', $plan);

        $plan->delete();

        return redirect()->route('coach.plans.index');
    }

    /** Assignments change only when the client explicitly sends clientIds. */
    private function maybeAssign(Request $request, WorkoutPlan $plan): void
    {
        if (! $request->has('clientIds')) {
            return;
        }

        $activeFrom = $request->input('activeFrom') ? Carbon::parse($request->input('activeFrom')) : null;

        $this->writer->syncAssignments($plan, $request->input('clientIds', []), $activeFrom);
    }

    /** @return Collection<int, array{id: int, name: string}> */
    private function athletes(Coach $coach): Collection
    {
        return $coach->athletes()->with('user')->get()
            ->map(fn (Client $client): array => [
                'id' => $client->id,
                'name' => $client->user?->name ?? '',
            ]);
    }
}
