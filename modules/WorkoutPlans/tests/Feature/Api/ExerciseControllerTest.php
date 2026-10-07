<?php

namespace Modules\WorkoutPlans\Tests\Feature\Api;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Auth\Http\Middleware\JwtAuthenticate;
use Modules\Auth\Http\Middleware\RequireVerified;
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

        Http::fake(['*' => Http::response(['thumbnail_url' => 'https://i.ytimg.com/vi/abc123/hq.jpg'])]);

        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->coach = Coach::factory()->create(['user_id' => $this->user->id]);
        $this->user->assignRole(Role::COACH);
    }

    private function asCoach(): self
    {
        $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($this->user);

        return $this;
    }

    public function test_authenticated_user_can_list_exercises(): void
    {
        Exercise::factory()->count(3)->global()->create();
        Exercise::factory()->count(2)->create(['coach_id' => $this->coach->id]);

        $response = $this->asCoach()->getJson('/api/v1/exercises');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'category', 'isCustom']]])
            ->assertJsonCount(5, 'data');
    }

    public function test_exercises_are_ordered_custom_first(): void
    {
        Exercise::factory()->count(2)->global()->create();
        Exercise::factory()->create(['coach_id' => $this->coach->id, 'name' => 'My Custom Exercise']);

        $response = $this->asCoach()->getJson('/api/v1/exercises');

        $response->assertOk();
        $this->assertEquals('My Custom Exercise', $response->json('data.0.name'));
    }

    public function test_exercises_can_be_filtered_by_search(): void
    {
        Exercise::factory()->create(['name' => 'Bench Press', 'coach_id' => null]);
        Exercise::factory()->create(['name' => 'Squat', 'coach_id' => null]);

        $response = $this->asCoach()->getJson('/api/v1/exercises?search=bench');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals('Bench Press', $response->json('data.0.name'));
    }

    public function test_exercises_can_be_filtered_by_type(): void
    {
        Exercise::factory()->strength()->create(['coach_id' => null]);
        Exercise::factory()->create(['category' => 'cardio', 'coach_id' => null]);

        $response = $this->asCoach()->getJson('/api/v1/exercises?type=strength');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_exercises_are_paginated(): void
    {
        Exercise::factory()->count(25)->global()->create();

        $response = $this->asCoach()->getJson('/api/v1/exercises?limit=10');

        $response->assertOk()->assertJsonCount(10, 'data');
        $response->assertJsonStructure(['data', 'meta' => ['total', 'per_page', 'current_page']]);
    }

    public function test_unauthenticated_user_cannot_list_exercises(): void
    {
        $this->getJson('/api/v1/exercises')->assertUnauthorized();
    }

    public function test_coach_can_create_custom_exercise(): void
    {
        $response = $this->asCoach()->postJson('/api/v1/coaches/me/exercises', [
            'name' => 'Cable Fly',
            'primaryMuscles' => ['chest'],
            'secondaryMuscles' => ['shoulders'],
            'type' => 'strength',
            'instructions' => 'Keep elbows slightly bent.',
            'videoUrl' => 'https://youtube.com/watch?v=abc123',
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'Cable Fly');
        $this->assertDatabaseHas('exercises', ['name' => 'Cable Fly', 'coach_id' => $this->coach->id]);
    }

    public function test_coach_can_update_own_custom_exercise(): void
    {
        $exercise = Exercise::factory()->create(['coach_id' => $this->coach->id, 'name' => 'Old Name']);

        $response = $this->asCoach()->putJson("/api/v1/coaches/me/exercises/{$exercise->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'New Name');
    }

    public function test_coach_cannot_update_public_exercise(): void
    {
        $exercise = Exercise::factory()->global()->create();

        $this->asCoach()->putJson("/api/v1/coaches/me/exercises/{$exercise->id}", [
            'name' => 'Hacked',
        ])->assertForbidden();
    }

    public function test_coach_can_delete_own_custom_exercise(): void
    {
        $exercise = Exercise::factory()->create(['coach_id' => $this->coach->id]);

        $this->asCoach()->deleteJson("/api/v1/coaches/me/exercises/{$exercise->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('exercises', ['id' => $exercise->id]);
    }

    public function test_coach_cannot_delete_public_exercise(): void
    {
        $exercise = Exercise::factory()->global()->create();

        $this->asCoach()->deleteJson("/api/v1/coaches/me/exercises/{$exercise->id}")
            ->assertForbidden();
    }

    public function test_deleting_an_exercise_in_use_returns_422(): void
    {
        $exercise = Exercise::factory()->create(['coach_id' => $this->coach->id]);
        $plan = WorkoutPlan::factory()->create(['coach_id' => $this->coach->id]);
        $day = WorkoutDay::factory()->create(['workout_plan_id' => $plan->id]);
        WorkoutExercise::factory()->create([
            'workout_plan_id' => $plan->id,
            'workout_day_id' => $day->id,
            'exercise_id' => $exercise->id,
        ]);

        $this->asCoach()
            ->deleteJson("/api/v1/coaches/me/exercises/{$exercise->id}")
            ->assertUnprocessable()
            ->assertJsonPath('plansCount', 1);
    }

    public function test_store_exercise_validates_required_name(): void
    {
        $this->asCoach()->postJson('/api/v1/coaches/me/exercises', [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['fields' => ['name']]]);
    }
}
