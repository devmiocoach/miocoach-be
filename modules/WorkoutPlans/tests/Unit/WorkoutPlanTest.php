<?php

namespace Modules\WorkoutPlans\Tests\Unit;

use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\WorkoutPlans\Enums\ExerciseDifficulty;
use Modules\WorkoutPlans\Enums\WorkoutGoal;
use Modules\WorkoutPlans\Models\Exercise;
use Modules\WorkoutPlans\Models\WorkoutExercise;
use Modules\WorkoutPlans\Models\WorkoutPlan;
use Tests\TestCase;

class WorkoutPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_scope_excludes_inactive_plans(): void
    {
        WorkoutPlan::factory()->create();
        WorkoutPlan::factory()->inactive()->create();

        $this->assertCount(1, WorkoutPlan::active()->get());
    }

    public function test_templates_scope_returns_only_templates(): void
    {
        WorkoutPlan::factory()->template()->create();
        WorkoutPlan::factory()->create();

        $this->assertCount(1, WorkoutPlan::templates()->get());
    }

    public function test_for_coach_scope_filters_by_coach(): void
    {
        $coach = Coach::factory()->create();
        WorkoutPlan::factory()->forCoach($coach)->create();
        WorkoutPlan::factory()->create();

        $results = WorkoutPlan::forCoach($coach->id)->get();

        $this->assertCount(1, $results);
        $this->assertEquals($coach->id, $results->first()->coach_id);
    }

    public function test_goal_and_difficulty_cast_to_enums(): void
    {
        $plan = WorkoutPlan::factory()->create([
            'goal' => WorkoutGoal::Strength,
            'difficulty' => ExerciseDifficulty::Advanced,
        ]);

        $fresh = $plan->fresh();

        $this->assertInstanceOf(WorkoutGoal::class, $fresh->goal);
        $this->assertSame(WorkoutGoal::Strength, $fresh->goal);
        $this->assertInstanceOf(ExerciseDifficulty::class, $fresh->difficulty);
        $this->assertSame(ExerciseDifficulty::Advanced, $fresh->difficulty);
    }

    public function test_flags_cast_to_boolean(): void
    {
        $plan = WorkoutPlan::factory()->template()->create();

        $this->assertIsBool($plan->fresh()->is_template);
        $this->assertIsBool($plan->fresh()->is_active);
    }

    public function test_belongs_to_coach(): void
    {
        $coach = Coach::factory()->create();
        $plan = WorkoutPlan::factory()->forCoach($coach)->create();

        $this->assertTrue($plan->coach->is($coach));
    }

    public function test_workout_exercises_relationship_returns_exercises_with_data(): void
    {
        $plan = WorkoutPlan::factory()->create();
        $exercise = Exercise::factory()->create();

        WorkoutExercise::create([
            'workout_plan_id' => $plan->id,
            'exercise_id' => $exercise->id,
            'week_number' => 1,
            'day_number' => 2,
            'order_index' => 0,
            'sets' => 3,
            'reps_target' => 10,
        ]);

        $workoutExercise = $plan->workoutExercises()->first();

        $this->assertNotNull($workoutExercise);
        $this->assertEquals($exercise->id, $workoutExercise->exercise_id);
        $this->assertEquals(3, $workoutExercise->sets);
        $this->assertEquals(10, $workoutExercise->reps_target);
    }
}
