<?php

namespace Modules\WorkoutPlans\Tests\Feature\Api;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Http\Middleware\JwtAuthenticate;
use Modules\Auth\Http\Middleware\RequireVerified;
use Modules\WorkoutPlans\Models\Exercise;
use Modules\WorkoutPlans\Models\PlanAssignment;
use Modules\WorkoutPlans\Models\WorkoutDay;
use Modules\WorkoutPlans\Models\WorkoutExercise;
use Modules\WorkoutPlans\Models\WorkoutPlan;
use Tests\TestCase;

class WorkoutPlanControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Coach $coach;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_coach_can_create_workout_plan_with_days_and_exercises(): void
    {
        $exercise = Exercise::factory()->global()->create();

        $response = $this->asCoach()->postJson('/api/v1/coaches/me/workout-plans', [
            'name' => 'My Strength Plan',
            'description' => 'A 4-week strength program.',
            'isTemplate' => false,
            'days' => [
                [
                    'dayNumber' => 1,
                    'name' => 'Push Day',
                    'exercises' => [
                        [
                            'exerciseId' => $exercise->id,
                            'sets' => 4,
                            'repsTarget' => 10,
                            'weightKg' => 80.0,
                            'rirTarget' => 2,
                            'restSeconds' => 90,
                            'orderIndex' => 0,
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('workout_plans', ['name' => 'My Strength Plan', 'coach_id' => $this->coach->id]);
        $this->assertDatabaseHas('workout_days', ['day_number' => 1, 'name' => 'Push Day']);
        $this->assertDatabaseHas('workout_exercises', ['exercise_id' => $exercise->id, 'sets' => 4, 'reps_target' => 10]);
    }

    public function test_store_validates_required_name(): void
    {
        $this->asCoach()->postJson('/api/v1/coaches/me/workout-plans', [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['fields' => ['name']]]);
    }

    public function test_unauthenticated_cannot_create_plan(): void
    {
        $this->postJson('/api/v1/coaches/me/workout-plans', ['name' => 'Test'])
            ->assertUnauthorized();
    }

    public function test_coach_can_list_own_workout_plans(): void
    {
        WorkoutPlan::factory()->forCoach($this->coach)->count(3)->create();
        WorkoutPlan::factory()->count(2)->create(); // another coach

        $response = $this->asCoach()->getJson('/api/v1/coaches/me/workout-plans');

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_can_get_workout_plan_detail(): void
    {
        $plan = WorkoutPlan::factory()->forCoach($this->coach)->create();
        WorkoutDay::factory()->create(['workout_plan_id' => $plan->id]);

        $response = $this->asCoach()->getJson("/api/v1/workout-plans/{$plan->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $plan->id)
            ->assertJsonStructure(['data' => ['id', 'name', 'days']]);
    }

    public function test_assigned_client_can_view_plan_detail(): void
    {
        $plan = WorkoutPlan::factory()->forCoach($this->coach)->create();

        $clientUser = User::factory()->create(['email_verified_at' => now()]);
        $clientUser->assignRole(Role::CLIENT);
        $client = Client::factory()->create(['user_id' => $clientUser->id]);
        PlanAssignment::create([
            'workout_plan_id' => $plan->id,
            'client_id' => $client->id,
            'assigned_at' => now(),
        ]);

        $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($clientUser)
            ->getJson("/api/v1/workout-plans/{$plan->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $plan->id);
    }

    public function test_non_owner_cannot_view_plan_detail(): void
    {
        $otherPlan = WorkoutPlan::factory()->create(); // belongs to another coach

        $this->asCoach()->getJson("/api/v1/workout-plans/{$otherPlan->id}")
            ->assertForbidden();
    }

    public function test_coach_can_update_own_workout_plan(): void
    {
        $plan = WorkoutPlan::factory()->forCoach($this->coach)->create(['name' => 'Old Name']);

        $response = $this->asCoach()->putJson("/api/v1/workout-plans/{$plan->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'New Name');
        $this->assertDatabaseHas('workout_plans', ['id' => $plan->id, 'name' => 'New Name']);
    }

    public function test_coach_can_soft_delete_own_workout_plan(): void
    {
        $plan = WorkoutPlan::factory()->forCoach($this->coach)->create();

        $this->asCoach()->deleteJson("/api/v1/workout-plans/{$plan->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('workout_plans', ['id' => $plan->id]);
    }

    public function test_coach_cannot_update_another_coachs_plan(): void
    {
        $otherPlan = WorkoutPlan::factory()->create();

        $this->asCoach()->putJson("/api/v1/workout-plans/{$otherPlan->id}", [
            'name' => 'Hacked',
        ])->assertForbidden();
    }

    public function test_coach_can_duplicate_workout_plan(): void
    {
        $plan = WorkoutPlan::factory()->forCoach($this->coach)->create(['name' => 'My Plan', 'is_template' => false]);
        $day = WorkoutDay::factory()->create(['workout_plan_id' => $plan->id]);
        $exercise = Exercise::factory()->global()->create();
        WorkoutExercise::create([
            'workout_plan_id' => $plan->id,
            'workout_day_id' => $day->id,
            'exercise_id' => $exercise->id,
            'sets' => 3,
        ]);

        $response = $this->asCoach()->postJson("/api/v1/workout-plans/{$plan->id}/duplicate");

        $response->assertCreated()->assertJsonPath('data.name', 'My Plan (copia)');
        $this->assertDatabaseCount('workout_plans', 2);
        $this->assertDatabaseCount('workout_days', 2);
        $this->assertDatabaseCount('workout_exercises', 2);
    }

    public function test_coach_can_assign_plan_to_clients(): void
    {
        $plan = WorkoutPlan::factory()->forCoach($this->coach)->create();
        $clients = Client::factory()->count(2)->create();
        $this->coach->athletes()->attach($clients->pluck('id'));

        $response = $this->asCoach()->postJson("/api/v1/workout-plans/{$plan->id}/assign", [
            'clientIds' => $clients->pluck('id')->toArray(),
            'activeFrom' => '2026-07-01',
        ]);

        $response->assertOk();
        $this->assertDatabaseCount('plan_assignments', 2);
        $this->assertDatabaseHas('plan_assignments', [
            'workout_plan_id' => $plan->id,
            'client_id' => $clients->first()->id,
        ]);
    }

    public function test_assign_validates_client_ids(): void
    {
        $plan = WorkoutPlan::factory()->forCoach($this->coach)->create();

        $this->asCoach()->postJson("/api/v1/workout-plans/{$plan->id}/assign", [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['fields' => ['clientIds']]]);
    }

    public function test_duplicating_a_non_template_plan_yields_a_non_template(): void
    {
        $plan = WorkoutPlan::factory()->create([
            'coach_id' => $this->coach->id,
            'is_template' => false,
        ]);

        $response = $this->asCoach()->postJson("/api/v1/workout-plans/{$plan->id}/duplicate");

        $response->assertCreated();
        $this->assertDatabaseHas('workout_plans', [
            'id' => $response->json('data.id'),
            'is_template' => false,
        ]);
    }

    public function test_duplicating_a_template_yields_a_template(): void
    {
        $plan = WorkoutPlan::factory()->create([
            'coach_id' => $this->coach->id,
            'is_template' => true,
        ]);

        $response = $this->asCoach()->postJson("/api/v1/workout-plans/{$plan->id}/duplicate");

        $response->assertCreated();
        $this->assertDatabaseHas('workout_plans', [
            'id' => $response->json('data.id'),
            'is_template' => true,
        ]);
    }

    public function test_updating_a_plan_without_days_leaves_the_tree_intact(): void
    {
        $exercise = Exercise::factory()->global()->create();
        $plan = WorkoutPlan::factory()->create(['coach_id' => $this->coach->id, 'name' => 'Vecchio']);
        $day = WorkoutDay::factory()->create(['workout_plan_id' => $plan->id, 'day_number' => 1]);
        WorkoutExercise::factory()->create([
            'workout_plan_id' => $plan->id,
            'workout_day_id' => $day->id,
            'exercise_id' => $exercise->id,
        ]);

        $this->asCoach()->putJson("/api/v1/workout-plans/{$plan->id}", ['name' => 'Nuovo'])
            ->assertOk();

        $this->assertDatabaseHas('workout_plans', ['id' => $plan->id, 'name' => 'Nuovo']);
        $this->assertDatabaseCount('workout_exercises', 1);
        $this->assertDatabaseCount('workout_days', 1);
    }

    public function test_updating_a_plan_with_days_replaces_the_tree(): void
    {
        $exercise = Exercise::factory()->global()->create();
        $plan = WorkoutPlan::factory()->create(['coach_id' => $this->coach->id]);
        $day = WorkoutDay::factory()->create(['workout_plan_id' => $plan->id, 'day_number' => 1]);
        $row = WorkoutExercise::factory()->create([
            'workout_plan_id' => $plan->id,
            'workout_day_id' => $day->id,
            'exercise_id' => $exercise->id,
        ]);

        $this->asCoach()->putJson("/api/v1/workout-plans/{$plan->id}", [
            'name' => $plan->name,
            'days' => [
                [
                    'id' => $day->id,
                    'dayNumber' => 1,
                    'name' => 'Rinominato',
                    'exercises' => [
                        ['id' => $row->id, 'exerciseId' => $exercise->id, 'sets' => 5],
                    ],
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('workout_days', ['id' => $day->id, 'name' => 'Rinominato']);
        $this->assertDatabaseHas('workout_exercises', ['id' => $row->id, 'sets' => 5]);
    }

    public function test_updating_with_days_but_no_name_is_rejected(): void
    {
        $plan = WorkoutPlan::factory()->forCoach($this->coach)->create();

        $this->asCoach()->putJson("/api/v1/workout-plans/{$plan->id}", [
            'days' => [
                ['dayNumber' => 1, 'name' => 'Push Day'],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['fields' => ['name']]]);
    }

    public function test_updating_with_a_foreign_day_id_is_rejected(): void
    {
        $planA = WorkoutPlan::factory()->forCoach($this->coach)->create();
        WorkoutDay::factory()->create(['workout_plan_id' => $planA->id, 'day_number' => 1]);

        $planB = WorkoutPlan::factory()->forCoach($this->coach)->create();
        $dayB = WorkoutDay::factory()->create(['workout_plan_id' => $planB->id, 'day_number' => 1]);

        $this->asCoach()->putJson("/api/v1/workout-plans/{$planA->id}", [
            'name' => $planA->name,
            'days' => [
                ['id' => $dayB->id, 'dayNumber' => 1, 'name' => 'Push Day'],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['fields' => ['days.0.id']]]);
    }

    public function test_updating_with_a_foreign_exercise_row_id_is_rejected(): void
    {
        $exercise = Exercise::factory()->global()->create();

        $planA = WorkoutPlan::factory()->forCoach($this->coach)->create();
        $dayA = WorkoutDay::factory()->create(['workout_plan_id' => $planA->id, 'day_number' => 1]);

        $planB = WorkoutPlan::factory()->forCoach($this->coach)->create();
        $dayB = WorkoutDay::factory()->create(['workout_plan_id' => $planB->id, 'day_number' => 1]);
        $rowB = WorkoutExercise::factory()->create([
            'workout_plan_id' => $planB->id,
            'workout_day_id' => $dayB->id,
            'exercise_id' => $exercise->id,
        ]);

        $this->asCoach()->putJson("/api/v1/workout-plans/{$planA->id}", [
            'name' => $planA->name,
            'days' => [
                [
                    'id' => $dayA->id,
                    'dayNumber' => 1,
                    'name' => 'Push Day',
                    'exercises' => [
                        ['id' => $rowB->id, 'exerciseId' => $exercise->id, 'sets' => 3],
                    ],
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['fields' => ['days.0.exercises.0.id']]]);
    }

    public function test_creating_a_plan_with_a_child_id_is_rejected(): void
    {
        $exercise = Exercise::factory()->global()->create();

        $response = $this->asCoach()->postJson('/api/v1/coaches/me/workout-plans', [
            'name' => 'My Strength Plan',
            'days' => [
                [
                    'id' => 999,
                    'dayNumber' => 1,
                    'name' => 'Push Day',
                    'exercises' => [
                        ['exerciseId' => $exercise->id, 'sets' => 4],
                    ],
                ],
            ],
        ]);

        $response->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['days.0.id']]]);
    }
}
