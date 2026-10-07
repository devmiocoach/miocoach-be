<?php

namespace Modules\WorkoutPlans\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\WorkoutPlans\Models\Exercise;
use Modules\WorkoutPlans\Models\WorkoutDay;
use Modules\WorkoutPlans\Models\WorkoutExercise;
use Modules\WorkoutPlans\Models\WorkoutPlan;
use Tests\TestCase;

class CleanupSchemaMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dead_columns_are_gone(): void
    {
        $this->assertFalse(Schema::hasColumn('workout_exercises', 'order'));
        $this->assertFalse(Schema::hasColumn('workout_exercises', 'reps'));
        $this->assertFalse(Schema::hasColumn('workout_plans', 'client_id'));
    }

    public function test_surviving_columns_are_untouched(): void
    {
        $this->assertTrue(Schema::hasColumn('workout_exercises', 'order_index'));
        $this->assertTrue(Schema::hasColumn('workout_exercises', 'reps_target'));
        $this->assertTrue(Schema::hasColumn('workout_exercises', 'week_number'));
        $this->assertTrue(Schema::hasColumn('workout_plans', 'duration_weeks'));
    }

    public function test_deleting_an_exercise_used_by_a_plan_is_blocked_at_the_database(): void
    {
        $plan = WorkoutPlan::factory()->create();
        $day = WorkoutDay::factory()->create(['workout_plan_id' => $plan->id]);
        $exercise = Exercise::factory()->global()->create();

        WorkoutExercise::factory()->create([
            'workout_plan_id' => $plan->id,
            'workout_day_id' => $day->id,
            'exercise_id' => $exercise->id,
        ]);

        $this->expectException(QueryException::class);

        // forceDelete bypasses SoftDeletes and hits the FK constraint.
        $exercise->forceDelete();
    }
}
