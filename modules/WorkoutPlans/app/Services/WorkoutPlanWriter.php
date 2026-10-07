<?php

namespace Modules\WorkoutPlans\Services;

use App\Modules\Users\Models\Coach;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\WorkoutPlans\Data\PlanDayData;
use Modules\WorkoutPlans\Data\PlanTreeData;
use Modules\WorkoutPlans\Models\PlanAssignment;
use Modules\WorkoutPlans\Models\WorkoutDay;
use Modules\WorkoutPlans\Models\WorkoutExercise;
use Modules\WorkoutPlans\Models\WorkoutPlan;

class WorkoutPlanWriter
{
    /**
     * Reconcile the stored plan tree against the submitted one.
     *
     * Rows are matched by id so that workout_exercises.id survives a save;
     * rows absent from the payload are deleted. Assignments are untouched —
     * see syncAssignments().
     */
    public function write(Coach $coach, ?WorkoutPlan $plan, PlanTreeData $data): WorkoutPlan
    {
        return DB::transaction(function () use ($coach, $plan, $data): WorkoutPlan {
            $attributes = [
                'name' => $data->name,
                'description' => $data->description,
                'goal' => $data->goal,
                'difficulty' => $data->difficulty,
                'duration_weeks' => $data->durationWeeks,
                'sessions_per_week' => $data->sessionsPerWeek,
                'is_template' => $data->isTemplate,
            ];

            if ($plan === null) {
                $plan = WorkoutPlan::create($attributes + [
                    'coach_id' => $coach->id,
                    'is_active' => true,
                ]);
            } else {
                $plan->update($attributes);
            }

            $this->syncDays($plan, $data->days);

            return $plan->fresh(['workoutDays.workoutExercises.exercise']);
        });
    }

    /** @param array<int, PlanDayData> $days */
    private function syncDays(WorkoutPlan $plan, array $days): void
    {
        $keptDayIds = [];
        $keptExerciseRowIds = [];

        // Upsert every day and its exercises first, across the whole payload,
        // before any deletion runs. An exercise moving from day A to day B in
        // the same save is still "kept" even though day A no longer lists it —
        // running deletions per-day inline would delete it out from under the
        // move before day B's turn to reparent it.
        foreach ($days as $position => $dayData) {
            $day = $this->upsertDay($plan, $dayData, $position);
            $keptDayIds[] = $day->id;

            array_push($keptExerciseRowIds, ...$this->upsertExercises($plan, $day, $dayData));
        }

        // Plan-scoped, not day-scoped. Two things depend on this:
        // a row reparented above must not be caught by its old day's cleanup,
        // and a dropped day's exercises must still be reachable — workout_day_id
        // is nullOnDelete, so deleting the day would only orphan them, leaving
        // live rows attached to the plan that the builder never shows again.
        WorkoutExercise::query()
            ->where('workout_plan_id', $plan->id)
            ->whereNotIn('id', $keptExerciseRowIds ?: [0])
            ->delete();

        $plan->workoutDays()->whereNotIn('id', $keptDayIds ?: [0])->delete();
    }

    private function upsertDay(WorkoutPlan $plan, PlanDayData $dayData, int $position): WorkoutDay
    {
        $attributes = [
            'workout_plan_id' => $plan->id,
            'day_number' => $dayData->dayNumber,
            'name' => $dayData->name,
            'order_index' => $position,
        ];

        if ($dayData->id === null) {
            return WorkoutDay::create($attributes);
        }

        $day = $plan->workoutDays()->whereKey($dayData->id)->firstOrFail();
        $day->update($attributes);

        return $day;
    }

    /** @return array<int, int> The kept workout_exercises row ids for this day. */
    private function upsertExercises(WorkoutPlan $plan, WorkoutDay $day, PlanDayData $dayData): array
    {
        $keptExerciseRowIds = [];

        foreach ($dayData->exercises as $position => $exerciseData) {
            $attributes = [
                'workout_plan_id' => $plan->id,
                'workout_day_id' => $day->id,
                'exercise_id' => $exerciseData->exerciseId,
                'sets' => $exerciseData->sets,
                'reps_target' => $exerciseData->repsTarget,
                'weight_kg' => $exerciseData->weightKg,
                'rir_target' => $exerciseData->rirTarget,
                'rest_seconds' => $exerciseData->restSeconds,
                'notes' => $exerciseData->notes,
                'order_index' => $position,
            ];

            if ($exerciseData->id === null) {
                $row = WorkoutExercise::create($attributes);
            } else {
                // Scoped to the plan, not the day: an exercise can move between days.
                $row = WorkoutExercise::query()
                    ->where('workout_plan_id', $plan->id)
                    ->whereKey($exerciseData->id)
                    ->firstOrFail();
                $row->update($attributes);
            }

            $keptExerciseRowIds[] = $row->id;
        }

        return $keptExerciseRowIds;
    }

    /**
     * Sync plan_assignments against the selected clients.
     * Called only on "Save and Assign", never on auto-save.
     *
     * @param  array<int, int>  $clientIds
     */
    public function syncAssignments(WorkoutPlan $plan, array $clientIds, ?CarbonInterface $activeFrom): void
    {
        DB::transaction(function () use ($plan, $clientIds, $activeFrom): void {
            foreach ($clientIds as $clientId) {
                PlanAssignment::updateOrCreate(
                    ['workout_plan_id' => $plan->id, 'client_id' => $clientId],
                    ['active_from' => $activeFrom, 'assigned_at' => now()],
                );
            }

            PlanAssignment::query()
                ->where('workout_plan_id', $plan->id)
                ->whereNotIn('client_id', $clientIds ?: [0])
                ->delete();
        });
    }
}
