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
use Modules\WorkoutPlans\Models\PlanAssignment;
use Modules\WorkoutPlans\Models\WorkoutPlan;
use Modules\WorkoutPlans\Models\WorkoutSession;
use Tests\TestCase;

class ClientWorkoutControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $clientUser;

    private Client $client;

    private Coach $coach;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientUser = User::factory()->create(['email_verified_at' => now()]);
        $this->clientUser->assignRole(Role::CLIENT);
        $this->client = Client::factory()->create(['user_id' => $this->clientUser->id]);

        $coachUser = User::factory()->create(['email_verified_at' => now()]);
        $this->coach = Coach::factory()->create(['user_id' => $coachUser->id]);
    }

    private function asClient(): self
    {
        $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($this->clientUser);

        return $this;
    }

    public function test_client_lists_only_assigned_active_plans(): void
    {
        $assigned = WorkoutPlan::factory()->forCoach($this->coach)->create(['is_active' => true]);
        $inactive = WorkoutPlan::factory()->forCoach($this->coach)->create(['is_active' => false]);
        $notAssigned = WorkoutPlan::factory()->forCoach($this->coach)->create();

        PlanAssignment::create(['workout_plan_id' => $assigned->id, 'client_id' => $this->client->id, 'assigned_at' => now()]);
        PlanAssignment::create(['workout_plan_id' => $inactive->id, 'client_id' => $this->client->id, 'assigned_at' => now()]);

        $response = $this->asClient()->getJson('/api/v1/clients/me/workout-plans');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.plan.id', $assigned->id)
            ->assertJsonStructure(['data' => [['activeFrom', 'assignedAt', 'plan' => ['id', 'name']]]]);
    }

    public function test_client_lists_own_session_history(): void
    {
        WorkoutSession::factory()->count(2)->create(['client_id' => $this->client->id]);
        WorkoutSession::factory()->create(); // another client

        $response = $this->asClient()->getJson('/api/v1/clients/me/workout-sessions');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_client_lists_personal_records(): void
    {
        $exercise = Exercise::factory()->global()->create();
        PersonalRecord::create([
            'client_id' => $this->client->id,
            'exercise_id' => $exercise->id,
            'weight_kg' => 100.0,
            'recorded_at' => now(),
        ]);

        $response = $this->asClient()->getJson('/api/v1/clients/me/personal-records');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.weightKg', 100)
            ->assertJsonPath('data.0.exerciseId', $exercise->id);
    }

    public function test_coach_without_client_profile_is_forbidden(): void
    {
        $coachUser = User::factory()->create(['email_verified_at' => now()]);
        $coachUser->assignRole(Role::CLIENT); // pass the role gate, but has no client profile
        Coach::factory()->create(['user_id' => $coachUser->id]);

        $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coachUser)
            ->getJson('/api/v1/clients/me/workout-plans')
            ->assertForbidden();
    }
}
