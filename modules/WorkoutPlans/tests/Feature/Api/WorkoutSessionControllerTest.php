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
use Modules\WorkoutPlans\Models\PersonalRecord;
use Modules\WorkoutPlans\Models\WorkoutDay;
use Modules\WorkoutPlans\Models\WorkoutPlan;
use Modules\WorkoutPlans\Models\WorkoutSession;
use Tests\TestCase;

class WorkoutSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $coachUser;

    private Coach $coach;

    private User $clientUser;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coachUser = User::factory()->create(['email_verified_at' => now()]);
        $this->coach = Coach::factory()->create(['user_id' => $this->coachUser->id]);

        $this->clientUser = User::factory()->create(['email_verified_at' => now()]);
        $this->clientUser->assignRole(Role::CLIENT);
        $this->client = Client::factory()->create(['user_id' => $this->clientUser->id]);
        $this->coach->athletes()->attach($this->client->id);
    }

    private function asClient(): self
    {
        $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($this->clientUser);

        return $this;
    }

    public function test_can_start_workout_session(): void
    {
        $plan = WorkoutPlan::factory()->forCoach($this->coach)->create();
        $day = WorkoutDay::factory()->create(['workout_plan_id' => $plan->id]);

        $response = $this->asClient()->postJson('/api/v1/clients/me/workout-sessions/start', [
            'planId' => $plan->id,
            'dayId' => $day->id,
            'scheduledDate' => '2026-07-01',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('workout_sessions', [
            'client_id' => $this->client->id,
            'workout_plan_id' => $plan->id,
            'workout_day_id' => $day->id,
        ]);
    }

    public function test_can_complete_workout_session_and_calculates_volume(): void
    {
        $plan = WorkoutPlan::factory()->forCoach($this->coach)->create();
        $day = WorkoutDay::factory()->create(['workout_plan_id' => $plan->id]);
        $session = WorkoutSession::factory()->create([
            'client_id' => $this->client->id,
            'workout_plan_id' => $plan->id,
            'workout_day_id' => $day->id,
        ]);
        $exercise = Exercise::factory()->global()->create();

        $response = $this->asClient()->postJson(
            "/api/v1/clients/me/workout-sessions/{$session->id}/complete",
            [
                'sets' => [
                    ['exerciseId' => $exercise->id, 'setNumber' => 1, 'repsDone' => 10, 'weightKg' => 80.0, 'rirActual' => 2, 'isCompleted' => true],
                    ['exerciseId' => $exercise->id, 'setNumber' => 2, 'repsDone' => 8, 'weightKg' => 80.0, 'rirActual' => 1, 'isCompleted' => true],
                ],
                'durationMinutes' => 45,
                'notes' => 'Good session.',
            ]
        );

        $response->assertOk();
        $this->assertDatabaseHas('workout_sessions', [
            'id' => $session->id,
            'total_volume_kg' => 1440.0, // (10+8) * 80
            'duration_minutes' => 45,
        ]);
        $this->assertDatabaseCount('session_sets', 2);
    }

    public function test_completing_session_upserts_personal_record(): void
    {
        $plan = WorkoutPlan::factory()->forCoach($this->coach)->create();
        $day = WorkoutDay::factory()->create(['workout_plan_id' => $plan->id]);
        $session = WorkoutSession::factory()->create([
            'client_id' => $this->client->id,
            'workout_plan_id' => $plan->id,
            'workout_day_id' => $day->id,
        ]);
        $exercise = Exercise::factory()->global()->create();

        $this->asClient()->postJson(
            "/api/v1/clients/me/workout-sessions/{$session->id}/complete",
            [
                'sets' => [
                    ['exerciseId' => $exercise->id, 'setNumber' => 1, 'repsDone' => 5, 'weightKg' => 100.0, 'rirActual' => 0, 'isCompleted' => true],
                ],
                'durationMinutes' => 60,
            ]
        );

        $this->assertDatabaseHas('personal_records', [
            'client_id' => $this->client->id,
            'exercise_id' => $exercise->id,
            'weight_kg' => 100.0,
        ]);
    }

    public function test_personal_record_not_updated_if_lower_weight(): void
    {
        $plan = WorkoutPlan::factory()->forCoach($this->coach)->create();
        $day = WorkoutDay::factory()->create(['workout_plan_id' => $plan->id]);
        $exercise = Exercise::factory()->global()->create();

        PersonalRecord::create([
            'client_id' => $this->client->id,
            'exercise_id' => $exercise->id,
            'weight_kg' => 120.0,
            'recorded_at' => now()->subMonth(),
        ]);

        $session = WorkoutSession::factory()->create([
            'client_id' => $this->client->id,
            'workout_plan_id' => $plan->id,
            'workout_day_id' => $day->id,
        ]);

        $this->asClient()->postJson(
            "/api/v1/clients/me/workout-sessions/{$session->id}/complete",
            [
                'sets' => [
                    ['exerciseId' => $exercise->id, 'setNumber' => 1, 'repsDone' => 5, 'weightKg' => 100.0, 'rirActual' => 0, 'isCompleted' => true],
                ],
                'durationMinutes' => 60,
            ]
        );

        $this->assertDatabaseHas('personal_records', [
            'client_id' => $this->client->id,
            'exercise_id' => $exercise->id,
            'weight_kg' => 120.0,
        ]);
    }
}
