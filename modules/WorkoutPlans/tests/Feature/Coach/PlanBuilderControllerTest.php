<?php

namespace Modules\WorkoutPlans\Tests\Feature\Coach;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\WorkoutPlans\Models\Exercise;
use Modules\WorkoutPlans\Models\WorkoutDay;
use Modules\WorkoutPlans\Models\WorkoutExercise;
use Modules\WorkoutPlans\Models\WorkoutPlan;
use Tests\TestCase;

class PlanBuilderControllerTest extends TestCase
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

    public function test_guests_are_redirected_from_the_builder(): void
    {
        $this->get(route('coach.plans.create'))->assertRedirect();
    }

    public function test_a_client_cannot_reach_the_builder(): void
    {
        $client = User::factory()->create(['email_verified_at' => now()]);
        $client->assignRole(Role::CLIENT);

        $this->actingAs($client)->get(route('coach.plans.create'))->assertForbidden();
    }

    public function test_coach_sees_the_plan_list(): void
    {
        WorkoutPlan::factory()->create(['coach_id' => $this->coach->id, 'name' => 'Forza Base']);

        $this->actingAs($this->user)
            ->get(route('coach.plans.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkoutPlans::Coach/Plans/Index', false)
                ->has('plans', 1)
                ->where('plans.0.name', 'Forza Base')
                ->has('stats.plans')
                ->has('stats.exercises')
            );
    }

    public function test_the_plan_list_excludes_other_coaches_plans(): void
    {
        $other = Coach::factory()->create();
        WorkoutPlan::factory()->create(['coach_id' => $other->id]);

        $this->actingAs($this->user)
            ->get(route('coach.plans.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('plans', 0));
    }

    public function test_storing_a_plan_returns_json_with_the_new_id(): void
    {
        $exercise = Exercise::factory()->global()->create();

        $response = $this->actingAs($this->user)->postJson(route('coach.plans.store'), [
            'name' => 'Forza Base',
            'days' => [
                [
                    'id' => null,
                    'dayNumber' => 1,
                    'name' => 'Push',
                    'exercises' => [
                        ['id' => null, 'exerciseId' => $exercise->id, 'sets' => 4, 'repsTarget' => 8],
                    ],
                ],
            ],
        ]);

        $response->assertCreated()->assertJsonStructure(['plan' => ['id', 'name', 'days']]);
        $this->assertDatabaseHas('workout_plans', ['name' => 'Forza Base', 'coach_id' => $this->coach->id]);
    }

    public function test_a_coach_cannot_put_another_coachs_custom_exercise_in_a_plan(): void
    {
        $otherCoach = Coach::factory()->create();
        $theirExercise = Exercise::factory()->create(['coach_id' => $otherCoach->id]);

        $this->actingAs($this->user)->postJson(route('coach.plans.store'), [
            'name' => 'Furto',
            'days' => [[
                'id' => null,
                'dayNumber' => 1,
                'exercises' => [['id' => null, 'exerciseId' => $theirExercise->id]],
            ]],
        ])->assertUnprocessable()->assertJsonValidationErrorFor('days.0.exercises.0.exerciseId', 'error.fields');
    }

    public function test_a_coach_can_use_a_global_exercise(): void
    {
        $global = Exercise::factory()->global()->create();

        $this->actingAs($this->user)->postJson(route('coach.plans.store'), [
            'name' => 'Ok',
            'days' => [[
                'id' => null,
                'dayNumber' => 1,
                'exercises' => [['id' => null, 'exerciseId' => $global->id]],
            ]],
        ])->assertCreated();
    }

    public function test_updating_another_coachs_plan_is_forbidden(): void
    {
        $other = Coach::factory()->create();
        $plan = WorkoutPlan::factory()->create(['coach_id' => $other->id]);

        $this->actingAs($this->user)
            ->putJson(route('coach.plans.update', $plan), ['name' => 'Preso'])
            ->assertForbidden();
    }

    public function test_assigning_to_a_client_who_is_not_an_athlete_is_rejected(): void
    {
        $stranger = Client::factory()->create();
        $plan = WorkoutPlan::factory()->create(['coach_id' => $this->coach->id]);

        $this->actingAs($this->user)
            ->putJson(route('coach.plans.update', $plan), [
                'name' => $plan->name,
                'clientIds' => [$stranger->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('clientIds.0', 'error.fields');
    }

    public function test_assigning_to_an_athlete_creates_the_assignment(): void
    {
        $athlete = Client::factory()->create();
        $this->coach->athletes()->attach($athlete->id, ['status' => 'active']);
        $plan = WorkoutPlan::factory()->create(['coach_id' => $this->coach->id]);

        $this->actingAs($this->user)
            ->putJson(route('coach.plans.update', $plan), [
                'name' => $plan->name,
                'clientIds' => [$athlete->id],
            ])
            ->assertOk();

        $this->assertDatabaseHas('plan_assignments', [
            'workout_plan_id' => $plan->id,
            'client_id' => $athlete->id,
        ]);
    }

    public function test_an_update_without_client_ids_leaves_assignments_alone(): void
    {
        $athlete = Client::factory()->create();
        $this->coach->athletes()->attach($athlete->id, ['status' => 'active']);
        $plan = WorkoutPlan::factory()->create(['coach_id' => $this->coach->id]);

        $this->actingAs($this->user)->putJson(route('coach.plans.update', $plan), [
            'name' => $plan->name,
            'clientIds' => [$athlete->id],
        ])->assertOk();

        // An auto-save omits clientIds entirely.
        $this->actingAs($this->user)->putJson(route('coach.plans.update', $plan), [
            'name' => 'Auto-salvato',
        ])->assertOk();

        $this->assertDatabaseHas('plan_assignments', [
            'workout_plan_id' => $plan->id,
            'client_id' => $athlete->id,
        ]);
    }

    public function test_editing_a_plan_exposes_days_as_a_flat_array(): void
    {
        $plan = WorkoutPlan::factory()->create(['coach_id' => $this->coach->id]);
        $day = WorkoutDay::factory()->create(['workout_plan_id' => $plan->id]);
        $exercise = Exercise::factory()->global()->create();
        WorkoutExercise::factory()->create([
            'workout_plan_id' => $plan->id,
            'workout_day_id' => $day->id,
            'exercise_id' => $exercise->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('coach.plans.edit', $plan))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkoutPlans::Coach/Plans/Builder', false)
                // Regression: nested resource collections must resolve to plain
                // arrays, not a `{ data: [...] }` wrapper that breaks days.map().
                ->has('plan.days', 1)
                ->has('plan.days.0.exercises', 1)
                ->where('plan.days.0.id', $day->id)
            );
    }

    public function test_updating_own_plan_with_real_child_ids_succeeds(): void
    {
        $plan = WorkoutPlan::factory()->create(['coach_id' => $this->coach->id]);
        $day = WorkoutDay::factory()->create(['workout_plan_id' => $plan->id]);
        $exercise = Exercise::factory()->global()->create();
        $workoutExercise = WorkoutExercise::factory()->create([
            'workout_plan_id' => $plan->id,
            'workout_day_id' => $day->id,
            'exercise_id' => $exercise->id,
        ]);

        $this->actingAs($this->user)
            ->putJson(route('coach.plans.update', $plan), [
                'name' => $plan->name,
                'days' => [
                    [
                        'id' => $day->id,
                        'dayNumber' => $day->day_number,
                        'name' => 'Rinominato',
                        'exercises' => [
                            [
                                'id' => $workoutExercise->id,
                                'exerciseId' => $exercise->id,
                                'sets' => 9,
                            ],
                        ],
                    ],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('workout_days', ['id' => $day->id, 'name' => 'Rinominato']);
        $this->assertDatabaseHas('workout_exercises', ['id' => $workoutExercise->id, 'sets' => 9]);
    }

    public function test_updating_with_a_foreign_child_id_is_rejected(): void
    {
        $planA = WorkoutPlan::factory()->create(['coach_id' => $this->coach->id]);
        $planB = WorkoutPlan::factory()->create(['coach_id' => $this->coach->id]);
        $dayB = WorkoutDay::factory()->create(['workout_plan_id' => $planB->id]);

        $this->actingAs($this->user)
            ->putJson(route('coach.plans.update', $planA), [
                'name' => $planA->name,
                'days' => [
                    [
                        'id' => $dayB->id,
                        'dayNumber' => 1,
                        'name' => 'Intrusione',
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('days.0.id', 'error.fields');
    }
}
