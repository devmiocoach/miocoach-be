<?php

namespace Modules\WorkoutPlans\Tests\Unit;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\WorkoutPlans\Data\PlanDayData;
use Modules\WorkoutPlans\Data\PlanExerciseData;
use Modules\WorkoutPlans\Data\PlanTreeData;
use Modules\WorkoutPlans\Models\Exercise;
use Modules\WorkoutPlans\Services\WorkoutPlanWriter;
use Tests\TestCase;

class WorkoutPlanWriterTest extends TestCase
{
    use RefreshDatabase;

    private WorkoutPlanWriter $writer;

    private Coach $coach;

    private Exercise $squat;

    private Exercise $bench;

    protected function setUp(): void
    {
        parent::setUp();

        $this->writer = app(WorkoutPlanWriter::class);
        $this->coach = Coach::factory()->create();
        $this->squat = Exercise::factory()->global()->create(['name' => 'Squat']);
        $this->bench = Exercise::factory()->global()->create(['name' => 'Bench']);
    }

    private function tree(array $days, string $name = 'Piano'): PlanTreeData
    {
        return new PlanTreeData(name: $name, days: $days);
    }

    public function test_it_creates_a_plan_with_days_and_exercises(): void
    {
        $plan = $this->writer->write($this->coach, null, $this->tree([
            new PlanDayData(id: null, dayNumber: 1, name: 'Push', exercises: [
                new PlanExerciseData(id: null, exerciseId: $this->squat->id, sets: 3, repsTarget: 10),
            ]),
        ]));

        $this->assertSame($this->coach->id, $plan->coach_id);
        $this->assertCount(1, $plan->workoutDays);
        $this->assertCount(1, $plan->workoutDays[0]->workoutExercises);
        $this->assertSame(0, $plan->workoutDays[0]->workoutExercises[0]->order_index);
    }

    public function test_it_preserves_exercise_ids_across_saves(): void
    {
        $plan = $this->writer->write($this->coach, null, $this->tree([
            new PlanDayData(id: null, dayNumber: 1, name: 'Push', exercises: [
                new PlanExerciseData(id: null, exerciseId: $this->squat->id, sets: 3),
            ]),
        ]));

        $dayId = $plan->workoutDays[0]->id;
        $exerciseRowId = $plan->workoutDays[0]->workoutExercises[0]->id;

        $plan = $this->writer->write($this->coach, $plan, $this->tree([
            new PlanDayData(id: $dayId, dayNumber: 1, name: 'Push', exercises: [
                new PlanExerciseData(id: $exerciseRowId, exerciseId: $this->squat->id, sets: 5),
            ]),
        ]));

        $row = $plan->workoutDays[0]->workoutExercises[0];
        $this->assertSame($exerciseRowId, $row->id, 'The workout_exercises row must survive a save.');
        $this->assertSame(5, $row->sets);
    }

    public function test_order_index_follows_array_position(): void
    {
        $plan = $this->writer->write($this->coach, null, $this->tree([
            new PlanDayData(id: null, dayNumber: 1, name: 'Push', exercises: [
                new PlanExerciseData(id: null, exerciseId: $this->squat->id),
                new PlanExerciseData(id: null, exerciseId: $this->bench->id),
            ]),
        ]));

        $day = $plan->workoutDays[0];
        $squatRow = $day->workoutExercises->firstWhere('exercise_id', $this->squat->id);
        $benchRow = $day->workoutExercises->firstWhere('exercise_id', $this->bench->id);

        $this->assertSame(0, $squatRow->order_index);
        $this->assertSame(1, $benchRow->order_index);

        // Swap them and save again.
        $plan = $this->writer->write($this->coach, $plan, $this->tree([
            new PlanDayData(id: $day->id, dayNumber: 1, name: 'Push', exercises: [
                new PlanExerciseData(id: $benchRow->id, exerciseId: $this->bench->id),
                new PlanExerciseData(id: $squatRow->id, exerciseId: $this->squat->id),
            ]),
        ]));

        $day = $plan->workoutDays[0];
        $this->assertSame(0, $day->workoutExercises->firstWhere('exercise_id', $this->bench->id)->order_index);
        $this->assertSame(1, $day->workoutExercises->firstWhere('exercise_id', $this->squat->id)->order_index);
    }

    public function test_it_deletes_exercises_absent_from_the_payload(): void
    {
        $plan = $this->writer->write($this->coach, null, $this->tree([
            new PlanDayData(id: null, dayNumber: 1, name: 'Push', exercises: [
                new PlanExerciseData(id: null, exerciseId: $this->squat->id),
                new PlanExerciseData(id: null, exerciseId: $this->bench->id),
            ]),
        ]));

        $day = $plan->workoutDays[0];
        $keep = $day->workoutExercises->firstWhere('exercise_id', $this->squat->id);
        $drop = $day->workoutExercises->firstWhere('exercise_id', $this->bench->id);

        $this->writer->write($this->coach, $plan, $this->tree([
            new PlanDayData(id: $day->id, dayNumber: 1, name: 'Push', exercises: [
                new PlanExerciseData(id: $keep->id, exerciseId: $this->squat->id),
            ]),
        ]));

        $this->assertDatabaseHas('workout_exercises', ['id' => $keep->id]);
        $this->assertDatabaseMissing('workout_exercises', ['id' => $drop->id]);
    }

    public function test_it_deletes_days_absent_from_the_payload_and_their_exercises(): void
    {
        $plan = $this->writer->write($this->coach, null, $this->tree([
            new PlanDayData(id: null, dayNumber: 1, name: 'Push', exercises: [
                new PlanExerciseData(id: null, exerciseId: $this->squat->id),
            ]),
            new PlanDayData(id: null, dayNumber: 2, name: 'Pull', exercises: [
                new PlanExerciseData(id: null, exerciseId: $this->bench->id),
            ]),
        ]));

        $keepDay = $plan->workoutDays->firstWhere('day_number', 1);
        $dropDay = $plan->workoutDays->firstWhere('day_number', 2);
        $dropExerciseId = $dropDay->workoutExercises[0]->id;

        $this->writer->write($this->coach, $plan, $this->tree([
            new PlanDayData(id: $keepDay->id, dayNumber: 1, name: 'Push', exercises: [
                new PlanExerciseData(id: $keepDay->workoutExercises[0]->id, exerciseId: $this->squat->id),
            ]),
        ]));

        $this->assertDatabaseMissing('workout_days', ['id' => $dropDay->id]);
        $this->assertDatabaseMissing('workout_exercises', ['id' => $dropExerciseId]);
    }

    public function test_it_moves_an_exercise_between_days(): void
    {
        $plan = $this->writer->write($this->coach, null, $this->tree([
            new PlanDayData(id: null, dayNumber: 1, name: 'A', exercises: [
                new PlanExerciseData(id: null, exerciseId: $this->squat->id),
            ]),
            new PlanDayData(id: null, dayNumber: 2, name: 'B', exercises: []),
        ]));

        $dayA = $plan->workoutDays->firstWhere('day_number', 1);
        $dayB = $plan->workoutDays->firstWhere('day_number', 2);
        $rowId = $dayA->workoutExercises[0]->id;

        $plan = $this->writer->write($this->coach, $plan, $this->tree([
            new PlanDayData(id: $dayA->id, dayNumber: 1, name: 'A', exercises: []),
            new PlanDayData(id: $dayB->id, dayNumber: 2, name: 'B', exercises: [
                new PlanExerciseData(id: $rowId, exerciseId: $this->squat->id),
            ]),
        ]));

        $this->assertDatabaseHas('workout_exercises', [
            'id' => $rowId,
            'workout_day_id' => $dayB->id,
        ]);
    }

    public function test_it_does_not_touch_assignments(): void
    {
        $client = Client::factory()->create();
        $plan = $this->writer->write($this->coach, null, $this->tree([]));
        $this->writer->syncAssignments($plan, [$client->id], null);

        $this->writer->write($this->coach, $plan, $this->tree([], name: 'Rinominato'));

        $this->assertDatabaseHas('plan_assignments', [
            'workout_plan_id' => $plan->id,
            'client_id' => $client->id,
        ]);
    }

    public function test_sync_assignments_adds_and_removes(): void
    {
        $keep = Client::factory()->create();
        $drop = Client::factory()->create();
        $plan = $this->writer->write($this->coach, null, $this->tree([]));

        $this->writer->syncAssignments($plan, [$keep->id, $drop->id], null);
        $this->assertDatabaseCount('plan_assignments', 2);

        $this->writer->syncAssignments($plan, [$keep->id], null);

        $this->assertDatabaseHas('plan_assignments', ['workout_plan_id' => $plan->id, 'client_id' => $keep->id]);
        $this->assertDatabaseMissing('plan_assignments', ['workout_plan_id' => $plan->id, 'client_id' => $drop->id]);
    }

    public function test_a_failed_write_rolls_back_completely(): void
    {
        $plan = $this->writer->write($this->coach, null, $this->tree([
            new PlanDayData(id: null, dayNumber: 1, name: 'Push', exercises: [
                new PlanExerciseData(id: null, exerciseId: $this->squat->id),
            ]),
        ]));

        try {
            // exercise_id 999999 violates the foreign key.
            $this->writer->write($this->coach, $plan, $this->tree([
                new PlanDayData(id: $plan->workoutDays[0]->id, dayNumber: 1, name: 'Rinominato', exercises: [
                    new PlanExerciseData(id: null, exerciseId: 999999),
                ]),
            ]));
            $this->fail('Expected the write to throw.');
        } catch (\Throwable) {
            // expected
        }

        $this->assertDatabaseHas('workout_plans', ['id' => $plan->id, 'name' => 'Piano']);
        $this->assertDatabaseCount('workout_exercises', 1);
    }
}
