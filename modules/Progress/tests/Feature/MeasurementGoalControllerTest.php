<?php

namespace Modules\Progress\Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Http\Middleware\JwtAuthenticate;
use Modules\Auth\Http\Middleware\RequireVerified;
use Modules\Progress\Enums\MetricType;
use Modules\Progress\Models\MeasurementGoal;
use Tests\TestCase;

class MeasurementGoalControllerTest extends TestCase
{
    use RefreshDatabase;

    private Coach $coach;

    private User $coachUser;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->coach = Coach::factory()->create();
        /** @var User $coachUser */
        $coachUser = $this->coach->user;
        $this->coachUser = $coachUser;
        $this->coachUser->assignRole(Role::COACH);
        $this->coachUser->setRelation('coach', $this->coach);

        $this->client = Client::factory()->create();
        $this->coach->athletes()->attach($this->client->id, ['status' => 'active', 'started_at' => now()]);
    }

    public function test_coach_can_upsert_goal(): void
    {
        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($this->coachUser)
            ->putJson("/api/v1/coaches/me/clients/{$this->client->id}/measurement-goals/weight", [
                'target_value' => 75.0,
                'target_date' => '2026-12-31',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.target_value', 75);

        $this->assertDatabaseHas('measurement_goals', [
            'client_id' => $this->client->id,
            'coach_id' => $this->coach->id,
            'metric_type' => 'weight',
            'target_value' => 75.0,
        ]);
    }

    public function test_coach_can_update_existing_goal(): void
    {
        MeasurementGoal::factory()->create([
            'client_id' => $this->client->id,
            'coach_id' => $this->coach->id,
            'metric_type' => MetricType::Weight->value,
            'target_value' => 80.0,
        ]);

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($this->coachUser)
            ->putJson("/api/v1/coaches/me/clients/{$this->client->id}/measurement-goals/weight", [
                'target_value' => 75.0,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.target_value', 75);

        $this->assertDatabaseCount('measurement_goals', 1);
    }

    public function test_coach_can_list_goals(): void
    {
        MeasurementGoal::factory()->create([
            'client_id' => $this->client->id,
            'coach_id' => $this->coach->id,
            'metric_type' => MetricType::Weight->value,
        ]);
        MeasurementGoal::factory()->create([
            'client_id' => $this->client->id,
            'coach_id' => $this->coach->id,
            'metric_type' => MetricType::BodyFat->value,
        ]);

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($this->coachUser)
            ->getJson("/api/v1/coaches/me/clients/{$this->client->id}/measurement-goals");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_coach_can_delete_goal(): void
    {
        MeasurementGoal::factory()->create([
            'client_id' => $this->client->id,
            'coach_id' => $this->coach->id,
            'metric_type' => MetricType::Weight->value,
        ]);

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($this->coachUser)
            ->deleteJson("/api/v1/coaches/me/clients/{$this->client->id}/measurement-goals/weight");

        $response->assertNoContent();
        $this->assertDatabaseMissing('measurement_goals', [
            'client_id' => $this->client->id,
            'metric_type' => 'weight',
        ]);
    }

    public function test_upsert_rejects_invalid_metric_type(): void
    {
        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($this->coachUser)
            ->putJson("/api/v1/coaches/me/clients/{$this->client->id}/measurement-goals/invalid", [
                'target_value' => 75.0,
            ]);

        $response->assertUnprocessable();
    }
}
