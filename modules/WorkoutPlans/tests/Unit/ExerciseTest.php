<?php

namespace Modules\WorkoutPlans\Tests\Unit;

use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\WorkoutPlans\Enums\ExerciseCategory;
use Modules\WorkoutPlans\Enums\ExerciseDifficulty;
use Modules\WorkoutPlans\Models\Exercise;
use Tests\TestCase;

class ExerciseTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_scope_excludes_inactive_exercises(): void
    {
        Exercise::factory()->create();
        Exercise::factory()->inactive()->create();

        $this->assertCount(1, Exercise::active()->get());
    }

    public function test_global_scope_returns_only_exercises_without_coach(): void
    {
        Exercise::factory()->global()->create();
        Exercise::factory()->create(['coach_id' => Coach::factory()]);

        $results = Exercise::global()->get();

        $this->assertCount(1, $results);
        $this->assertNull($results->first()->coach_id);
    }

    public function test_for_coach_scope_includes_global_and_own_exercises(): void
    {
        $coach = Coach::factory()->create();
        $other = Coach::factory()->create();

        Exercise::factory()->global()->create();
        Exercise::factory()->create(['coach_id' => $coach->id]);
        Exercise::factory()->create(['coach_id' => $other->id]);

        $this->assertCount(2, Exercise::forCoach($coach->id)->get());
    }

    public function test_category_and_difficulty_cast_to_enums(): void
    {
        $exercise = Exercise::factory()->create([
            'category' => ExerciseCategory::Cardio,
            'difficulty' => ExerciseDifficulty::Beginner,
        ]);

        $fresh = $exercise->fresh();

        $this->assertInstanceOf(ExerciseCategory::class, $fresh->category);
        $this->assertSame(ExerciseCategory::Cardio, $fresh->category);
        $this->assertInstanceOf(ExerciseDifficulty::class, $fresh->difficulty);
    }

    public function test_muscle_groups_and_equipment_cast_to_array(): void
    {
        $exercise = Exercise::factory()->create([
            'muscle_groups' => ['chest', 'triceps'],
            'equipment' => ['barbell'],
        ]);

        $fresh = $exercise->fresh();

        $this->assertEquals(['chest', 'triceps'], $fresh->muscle_groups);
        $this->assertEquals(['barbell'], $fresh->equipment);
    }
}
