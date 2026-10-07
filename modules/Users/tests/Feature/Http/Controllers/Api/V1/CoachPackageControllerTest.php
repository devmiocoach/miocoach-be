<?php

namespace App\Modules\Users\Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachPackage;
use App\Modules\Users\Providers\UsersServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Http\Middleware\JwtAuthenticate;
use Modules\Auth\Http\Middleware\RequireVerified;
use Tests\TestCase;

class CoachPackageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->app->register(UsersServiceProvider::class);
    }

    private function actingAsCoach(): array
    {
        $coach = Coach::factory()->create();
        $user = $coach->user;
        $user->assignRole(Role::COACH);
        $user->setRelation('coach', $coach);

        return [$user, $coach];
    }

    public function test_index_returns_coach_packages(): void
    {
        [$user, $coach] = $this->actingAsCoach();
        CoachPackage::factory()->count(3)->create(['coach_id' => $coach->id]);
        CoachPackage::factory()->create(); // another coach's package

        $response = $this
            ->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->getJson('/api/v1/coaches/me/packages');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_store_creates_package(): void
    {
        [$user] = $this->actingAsCoach();

        $response = $this
            ->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->postJson('/api/v1/coaches/me/packages', [
                'name' => 'Starter Pack',
                'description' => '10 sessioni per iniziare',
                'sessions_count' => 10,
                'price_cents' => 19999,
                'validity_days' => 90,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Starter Pack')
            ->assertJsonPath('data.sessions_count', 10)
            ->assertJsonPath('data.price_cents', 19999);

        $this->assertDatabaseHas('coach_packages', ['name' => 'Starter Pack']);
    }

    public function test_store_validates_required_fields(): void
    {
        [$user] = $this->actingAsCoach();

        $response = $this
            ->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->postJson('/api/v1/coaches/me/packages', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'sessions_count', 'price_cents']);
    }

    public function test_update_modifies_package(): void
    {
        [$user, $coach] = $this->actingAsCoach();
        $package = CoachPackage::factory()->create(['coach_id' => $coach->id]);

        $response = $this
            ->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->putJson("/api/v1/coaches/me/packages/{$package->id}", [
                'name' => 'Updated Pack',
                'price_cents' => 29900,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Pack');

        $this->assertDatabaseHas('coach_packages', ['id' => $package->id, 'name' => 'Updated Pack']);
    }

    public function test_update_rejects_other_coach_package(): void
    {
        [$user] = $this->actingAsCoach();
        $otherPackage = CoachPackage::factory()->create();

        $response = $this
            ->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->putJson("/api/v1/coaches/me/packages/{$otherPackage->id}", [
                'name' => 'Hacked',
            ]);

        $response->assertForbidden();
    }

    public function test_destroy_deletes_package(): void
    {
        [$user, $coach] = $this->actingAsCoach();
        $package = CoachPackage::factory()->create(['coach_id' => $coach->id]);

        $response = $this
            ->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->deleteJson("/api/v1/coaches/me/packages/{$package->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('coach_packages', ['id' => $package->id]);
    }

    public function test_destroy_rejects_other_coach_package(): void
    {
        [$user] = $this->actingAsCoach();
        $otherPackage = CoachPackage::factory()->create();

        $response = $this
            ->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->deleteJson("/api/v1/coaches/me/packages/{$otherPackage->id}");

        $response->assertForbidden();
    }
}
