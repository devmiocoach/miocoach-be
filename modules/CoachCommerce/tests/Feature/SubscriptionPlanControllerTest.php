<?php

namespace Modules\CoachCommerce\Tests\Feature;

use App\Enums\Role;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Http\Middleware\JwtAuthenticate;
use Modules\Auth\Http\Middleware\RequireVerified;
use Modules\CoachCommerce\Models\SubscriptionPlan;
use Tests\TestCase;

class SubscriptionPlanControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsCoach(): array
    {
        $coach = Coach::factory()->create();
        $user = $coach->user;
        $user->assignRole(Role::COACH);
        $user->setRelation('coach', $coach);

        return [$user, $coach];
    }

    public function test_index_returns_coach_plans(): void
    {
        [$user, $coach] = $this->actingAsCoach();
        SubscriptionPlan::factory()->count(2)->create(['coach_id' => $coach->id]);
        SubscriptionPlan::factory()->create(); // another coach's plan

        $response = $this
            ->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->getJson('/api/v1/coaches/me/subscription-plans');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_store_creates_plan(): void
    {
        [$user] = $this->actingAsCoach();

        $response = $this
            ->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->postJson('/api/v1/coaches/me/subscription-plans', [
                'name' => 'Piano Premium',
                'description' => 'Accesso completo',
                'price_monthly' => 99.00,
                'price_yearly' => 899.00,
                'sessions_per_month' => 8,
                'features' => ['Chat illimitata', 'Schede personalizzate'],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Piano Premium')
            ->assertJsonPath('data.sessions_per_month', 8);

        $this->assertDatabaseHas('subscription_plans', ['name' => 'Piano Premium']);
    }

    public function test_store_validates_required_name(): void
    {
        [$user] = $this->actingAsCoach();

        $response = $this
            ->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->postJson('/api/v1/coaches/me/subscription-plans', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_modifies_plan(): void
    {
        [$user, $coach] = $this->actingAsCoach();
        $plan = SubscriptionPlan::factory()->create(['coach_id' => $coach->id]);

        $response = $this
            ->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->putJson("/api/v1/coaches/me/subscription-plans/{$plan->id}", [
                'name' => 'Piano Aggiornato',
                'price_monthly' => 129.00,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Piano Aggiornato');

        $this->assertDatabaseHas('subscription_plans', ['id' => $plan->id, 'name' => 'Piano Aggiornato']);
    }

    public function test_update_rejects_other_coach_plan(): void
    {
        [$user] = $this->actingAsCoach();
        $otherPlan = SubscriptionPlan::factory()->create();

        $response = $this
            ->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->putJson("/api/v1/coaches/me/subscription-plans/{$otherPlan->id}", [
                'name' => 'Hacked',
            ]);

        $response->assertForbidden();
    }

    public function test_destroy_deletes_plan(): void
    {
        [$user, $coach] = $this->actingAsCoach();
        $plan = SubscriptionPlan::factory()->create(['coach_id' => $coach->id]);

        $response = $this
            ->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->deleteJson("/api/v1/coaches/me/subscription-plans/{$plan->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('subscription_plans', ['id' => $plan->id]);
    }

    public function test_destroy_rejects_other_coach_plan(): void
    {
        [$user] = $this->actingAsCoach();
        $otherPlan = SubscriptionPlan::factory()->create();

        $response = $this
            ->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->deleteJson("/api/v1/coaches/me/subscription-plans/{$otherPlan->id}");

        $response->assertForbidden();
    }

    public function test_features_stored_and_returned_as_array(): void
    {
        [$user, $coach] = $this->actingAsCoach();
        $features = ['Chat', 'Schede', 'Video'];

        $this
            ->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->postJson('/api/v1/coaches/me/subscription-plans', [
                'name' => 'Test Plan',
                'features' => $features,
            ])
            ->assertCreated()
            ->assertJsonPath('data.features', $features);
    }
}
