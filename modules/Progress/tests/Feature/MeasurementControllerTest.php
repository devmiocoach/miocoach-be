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
use Modules\Progress\Models\BodyMeasurement;
use Modules\Progress\Models\MeasurementGoal;
use Tests\TestCase;

class MeasurementControllerTest extends TestCase
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

    public function test_coach_can_store_measurement(): void
    {
        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($this->coachUser)
            ->postJson("/api/v1/coaches/me/clients/{$this->client->id}/measurements", [
                'metric_type' => 'weight',
                'value' => 82.5,
                'measured_at' => '2026-06-15',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.metric_type', 'weight')
            ->assertJsonPath('data.value', 82.5);

        $this->assertDatabaseHas('body_measurements', [
            'client_id' => $this->client->id,
            'coach_id' => $this->coach->id,
            'metric_type' => 'weight',
            'value' => 82.5,
        ]);
    }

    public function test_coach_cannot_store_measurement_for_unowned_client(): void
    {
        $otherClient = Client::factory()->create();

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($this->coachUser)
            ->postJson("/api/v1/coaches/me/clients/{$otherClient->id}/measurements", [
                'metric_type' => 'weight',
                'value' => 80,
                'measured_at' => '2026-06-15',
            ]);

        $response->assertForbidden();
    }

    public function test_coach_with_suspended_link_cannot_store_measurement(): void
    {
        $suspendedClient = Client::factory()->create();
        $this->coach->athletes()->attach($suspendedClient->id, ['status' => 'suspended', 'started_at' => now()]);

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($this->coachUser)
            ->postJson("/api/v1/coaches/me/clients/{$suspendedClient->id}/measurements", [
                'metric_type' => 'weight',
                'value' => 80,
                'measured_at' => '2026-06-15',
            ]);

        $response->assertForbidden();
    }

    public function test_coach_with_pending_link_cannot_list_measurements(): void
    {
        $pendingClient = Client::factory()->create();
        $this->coach->athletes()->attach($pendingClient->id, ['status' => 'pending', 'started_at' => now()]);

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($this->coachUser)
            ->getJson("/api/v1/coaches/me/clients/{$pendingClient->id}/measurements");

        $response->assertForbidden();
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($this->coachUser)
            ->postJson("/api/v1/coaches/me/clients/{$this->client->id}/measurements", []);

        $response->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['metric_type', 'value', 'measured_at']]]);
    }

    public function test_store_validates_metric_type_enum(): void
    {
        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($this->coachUser)
            ->postJson("/api/v1/coaches/me/clients/{$this->client->id}/measurements", [
                'metric_type' => 'invalid_type',
                'value' => 80,
                'measured_at' => '2026-06-15',
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['metric_type']]]);
    }

    public function test_coach_can_list_measurements(): void
    {
        BodyMeasurement::factory()->count(5)->create([
            'client_id' => $this->client->id,
            'coach_id' => $this->coach->id,
            'metric_type' => MetricType::Weight->value,
        ]);

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($this->coachUser)
            ->getJson("/api/v1/coaches/me/clients/{$this->client->id}/measurements?metric_type=weight");

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_chart_endpoint_returns_data(): void
    {
        BodyMeasurement::factory()->count(3)->create([
            'client_id' => $this->client->id,
            'coach_id' => $this->coach->id,
            'metric_type' => MetricType::Weight->value,
            'value' => 80,
        ]);

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($this->coachUser)
            ->getJson("/api/v1/coaches/me/clients/{$this->client->id}/measurements/chart?metric_type=weight&range=all");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'metric_type',
                    'unit',
                    'points',
                    'goal',
                    'summary' => ['first_value', 'last_value', 'delta', 'delta_percent', 'min', 'max'],
                ],
            ]);
    }

    public function test_chart_includes_goal_when_set(): void
    {
        BodyMeasurement::factory()->create([
            'client_id' => $this->client->id,
            'coach_id' => $this->coach->id,
            'metric_type' => MetricType::Weight->value,
        ]);

        MeasurementGoal::factory()->create([
            'client_id' => $this->client->id,
            'coach_id' => $this->coach->id,
            'metric_type' => MetricType::Weight->value,
            'target_value' => 75.0,
        ]);

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($this->coachUser)
            ->getJson("/api/v1/coaches/me/clients/{$this->client->id}/measurements/chart?metric_type=weight&range=all");

        $response->assertOk()
            ->assertJsonPath('data.goal.target_value', 75);
    }

    public function test_coach_can_update_measurement(): void
    {
        /** @var BodyMeasurement $measurement */
        $measurement = BodyMeasurement::factory()->create([
            'client_id' => $this->client->id,
            'coach_id' => $this->coach->id,
            'metric_type' => MetricType::Weight->value,
            'value' => 80,
        ]);

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($this->coachUser)
            ->putJson("/api/v1/coaches/me/clients/{$this->client->id}/measurements/{$measurement->id}", [
                'value' => 79.5,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.value', 79.5);
    }

    public function test_coach_can_delete_measurement(): void
    {
        /** @var BodyMeasurement $measurement */
        $measurement = BodyMeasurement::factory()->create([
            'client_id' => $this->client->id,
            'coach_id' => $this->coach->id,
        ]);

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($this->coachUser)
            ->deleteJson("/api/v1/coaches/me/clients/{$this->client->id}/measurements/{$measurement->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('body_measurements', ['id' => $measurement->id]);
    }

    public function test_chart_requires_metric_type(): void
    {
        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($this->coachUser)
            ->getJson("/api/v1/coaches/me/clients/{$this->client->id}/measurements/chart");

        $response->assertUnprocessable();
    }
}
