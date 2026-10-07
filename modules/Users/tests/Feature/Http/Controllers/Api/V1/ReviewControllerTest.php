<?php

namespace App\Modules\Users\Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Enums\UserType;
use App\Models\User;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\Review;
use App\Modules\Users\Providers\UsersServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Http\Middleware\JwtAuthenticate;
use Modules\Auth\Http\Middleware\RequireVerified;
use Modules\Auth\Providers\AuthServiceProvider;
use Tests\TestCase;

class ReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->app->register(UsersServiceProvider::class);
        $this->app->register(AuthServiceProvider::class);
    }

    public function test_index_returns_published_reviews_for_coach(): void
    {
        $coach = Coach::factory()->create();

        Review::factory()->count(3)->create([
            'coach_id' => $coach->id,
            'published_at' => now(),
        ]);

        Review::factory()->create([
            'coach_id' => $coach->id,
            'published_at' => null,
        ]);

        $response = $this->getJson("/api/v1/coaches/{$coach->id}/reviews");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_index_paginates_results(): void
    {
        $coach = Coach::factory()->create();

        Review::factory()->count(15)->create([
            'coach_id' => $coach->id,
            'published_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/coaches/{$coach->id}/reviews?limit=5");

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
    }

    public function test_store_requires_authentication(): void
    {
        $coach = Coach::factory()->create();

        $response = $this->postJson("/api/v1/coaches/{$coach->id}/reviews", [
            'rating' => 5,
            'title' => 'Great',
            'body' => 'Amazing coach',
        ]);

        $response->assertUnauthorized();
    }

    public function test_store_creates_review_for_authenticated_client(): void
    {
        $coach = Coach::factory()->create();
        $user = User::factory()->create(['type' => UserType::ATLETA->value]);
        $user->assignRole(Role::CLIENT);
        $client = Client::factory()->create(['user_id' => $user->id]);

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->postJson("/api/v1/coaches/{$coach->id}/reviews", [
                'rating' => 4,
                'title' => 'Ottimo',
                'body' => 'Consigliato',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('reviews', [
            'coach_id' => $coach->id,
            'client_id' => $client->id,
            'rating' => 4,
        ]);
    }

    public function test_store_prevents_duplicate_reviews(): void
    {
        $coach = Coach::factory()->create();
        $user = User::factory()->create(['type' => UserType::ATLETA->value]);
        $user->assignRole(Role::CLIENT);
        $client = Client::factory()->create(['user_id' => $user->id]);

        Review::factory()->create([
            'coach_id' => $coach->id,
            'client_id' => $client->id,
            'published_at' => now(),
        ]);

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->postJson("/api/v1/coaches/{$coach->id}/reviews", [
                'rating' => 5,
            ]);

        $response->assertUnprocessable();
    }

    public function test_store_validates_rating_range(): void
    {
        $coach = Coach::factory()->create();
        $user = User::factory()->create(['type' => UserType::ATLETA->value]);
        $user->assignRole(Role::CLIENT);
        Client::factory()->create(['user_id' => $user->id]);

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->postJson("/api/v1/coaches/{$coach->id}/reviews", [
                'rating' => 6,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $response->assertJsonPath('error.fields.rating.0', 'Il campo rating non deve essere maggiore di 5.');
    }

    public function test_store_prevents_self_review(): void
    {
        $user = User::factory()->create(['type' => UserType::ATLETA->value]);
        $user->assignRole(Role::CLIENT);
        $coach = Coach::factory()->create(['user_id' => $user->id]);
        Client::factory()->create(['user_id' => $user->id]);

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->postJson("/api/v1/coaches/{$coach->id}/reviews", [
                'rating' => 5,
                'title' => 'Io sono bravissimo',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('message', 'Non puoi recensire te stesso.');
    }
}
