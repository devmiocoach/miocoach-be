<?php

namespace Modules\WorkoutPlans\Tests\Feature\Coach;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\WorkoutPlans\Models\Exercise;
use Modules\WorkoutPlans\Models\WorkoutDay;
use Modules\WorkoutPlans\Models\WorkoutExercise;
use Modules\WorkoutPlans\Models\WorkoutPlan;
use Tests\TestCase;

class ExerciseControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Coach $coach;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->user->assignRole(Role::COACH);
        $this->coach = Coach::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_the_library_lists_global_and_own_exercises_only(): void
    {
        $global = Exercise::factory()->global()->create(['name' => 'Squat']);
        $mine = Exercise::factory()->create(['coach_id' => $this->coach->id, 'name' => 'Mio']);
        Exercise::factory()->create(['coach_id' => Coach::factory()->create()->id, 'name' => 'Altrui']);

        $this->actingAs($this->user)
            ->get(route('coach.exercises.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkoutPlans::Coach/Exercises/Index', false)
                ->has('exercises', 2)
            );
    }

    public function test_creating_a_custom_exercise_resolves_a_thumbnail(): void
    {
        Http::fake(['*' => Http::response(['thumbnail_url' => 'https://i.ytimg.com/vi/abc/hq.jpg'])]);

        $this->actingAs($this->user)->postJson(route('coach.exercises.store'), [
            'name' => 'Front Squat',
            'type' => 'strength',
            'primaryMuscles' => ['legs'],
            'videoUrl' => 'https://youtu.be/abc',
        ])->assertCreated();

        $this->assertDatabaseHas('exercises', [
            'name' => 'Front Squat',
            'coach_id' => $this->coach->id,
            'thumbnail_url' => 'https://i.ytimg.com/vi/abc/hq.jpg',
        ]);
    }

    public function test_a_disallowed_video_host_is_rejected(): void
    {
        $this->actingAs($this->user)->postJson(route('coach.exercises.store'), [
            'name' => 'Sospetto',
            'videoUrl' => 'https://evil.example.com/v.mp4',
        ])->assertUnprocessable()->assertJsonValidationErrorFor('videoUrl', 'error.fields');
    }

    public function test_updating_the_video_clears_a_stale_thumbnail_when_resolution_fails(): void
    {
        $mine = Exercise::factory()->create([
            'coach_id' => $this->coach->id,
            'video_url' => 'https://youtu.be/old',
            'thumbnail_url' => 'https://i.ytimg.com/vi/old/hq.jpg',
        ]);

        // The new video's oEmbed lookup returns nothing usable.
        Http::fake(['*' => Http::response([], 404)]);

        $this->actingAs($this->user)
            ->putJson(route('coach.exercises.update', $mine), [
                'videoUrl' => 'https://youtu.be/new',
            ])->assertOk();

        $mine->refresh();

        $this->assertSame('https://youtu.be/new', $mine->video_url);
        $this->assertNull($mine->thumbnail_url);
    }

    public function test_type_and_primary_muscles_can_be_changed_and_cleared(): void
    {
        $mine = Exercise::factory()->create([
            'coach_id' => $this->coach->id,
            'category' => 'strength',
            'primary_muscles' => ['legs'],
        ]);

        $this->actingAs($this->user)
            ->putJson(route('coach.exercises.update', $mine), [
                'type' => 'mobility',
                'primaryMuscles' => ['hips', 'core'],
            ])->assertOk();

        $mine->refresh();
        $this->assertSame('mobility', $mine->category->value);
        $this->assertSame(['hips', 'core'], $mine->primary_muscles);

        $this->actingAs($this->user)
            ->putJson(route('coach.exercises.update', $mine), [
                'primaryMuscles' => [],
            ])->assertOk();

        $mine->refresh();
        $this->assertSame([], $mine->primary_muscles);
        $this->assertSame('mobility', $mine->category->value);
    }

    /**
     * exercises.category is NOT NULL with no default: a missing or bogus type
     * has to fail validation instead of blowing up on insert.
     */
    public function test_creating_an_exercise_requires_a_valid_type(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('coach.exercises.store'), ['name' => 'Senza tipo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('type', 'error.fields');

        $this->actingAs($this->user)
            ->postJson(route('coach.exercises.store'), ['name' => 'Tipo finto', 'type' => 'inventato'])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('type', 'error.fields');
    }

    public function test_a_coach_cannot_edit_a_global_exercise(): void
    {
        $global = Exercise::factory()->global()->create();

        $this->actingAs($this->user)
            ->putJson(route('coach.exercises.update', $global), ['name' => 'Rubato'])
            ->assertForbidden();
    }

    public function test_a_coach_cannot_edit_another_coachs_exercise(): void
    {
        $theirs = Exercise::factory()->create(['coach_id' => Coach::factory()->create()->id]);

        $this->actingAs($this->user)
            ->putJson(route('coach.exercises.update', $theirs), ['name' => 'Rubato'])
            ->assertForbidden();
    }

    public function test_deleting_an_unused_custom_exercise_succeeds(): void
    {
        $mine = Exercise::factory()->create(['coach_id' => $this->coach->id]);

        $this->actingAs($this->user)
            ->from(route('coach.exercises.index'))
            ->delete(route('coach.exercises.destroy', $mine))
            ->assertRedirect(route('coach.exercises.index'))
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('exercises', ['id' => $mine->id]);
    }

    public function test_deleting_a_custom_exercise_in_use_is_blocked(): void
    {
        $mine = Exercise::factory()->create(['coach_id' => $this->coach->id]);
        $plan = WorkoutPlan::factory()->create(['coach_id' => $this->coach->id]);
        $day = WorkoutDay::factory()->create(['workout_plan_id' => $plan->id]);
        WorkoutExercise::factory()->create([
            'workout_plan_id' => $plan->id,
            'workout_day_id' => $day->id,
            'exercise_id' => $mine->id,
        ]);

        $this->actingAs($this->user)
            ->from(route('coach.exercises.index'))
            ->delete(route('coach.exercises.destroy', $mine))
            ->assertRedirect(route('coach.exercises.index'))
            ->assertSessionHasErrors('exercise');

        $this->assertDatabaseHas('exercises', ['id' => $mine->id, 'deleted_at' => null]);
    }
}
