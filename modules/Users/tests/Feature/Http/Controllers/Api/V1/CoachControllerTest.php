<?php

namespace App\Modules\Users\Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Providers\UsersServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Http\Middleware\JwtAuthenticate;
use Modules\Auth\Http\Middleware\RequireVerified;
use Modules\Auth\Providers\AuthServiceProvider;
use Tests\TestCase;

/**
 * Contract of `GET /coaches/me`, which backs the mobile profile tab.
 */
class CoachControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->app->register(UsersServiceProvider::class);
        $this->app->register(AuthServiceProvider::class);
    }

    private function showAs(Coach $coach): \Illuminate\Testing\TestResponse
    {
        $user = $coach->user;
        $user->assignRole(Role::COACH);
        $user->setRelation('coach', $coach);

        return $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->getJson('/api/v1/coaches/me');
    }

    public function test_it_exposes_the_showcase_fields_the_mobile_profile_needs(): void
    {
        $coach = Coach::factory()->create([
            'tagline' => 'Forza e mobilità',
            'profile_photo_url' => 'https://cdn.test/profile.jpg',
            'cover_photo_url' => 'https://cdn.test/cover.jpg',
            'languages' => ['it', 'en'],
        ]);

        $this->showAs($coach)
            ->assertOk()
            ->assertJsonPath('data.tagline', 'Forza e mobilità')
            ->assertJsonPath('data.profile_photo_url', 'https://cdn.test/profile.jpg')
            ->assertJsonPath('data.cover_photo_url', 'https://cdn.test/cover.jpg')
            ->assertJsonPath('data.languages', ['it', 'en'])
            ->assertJsonPath('data.user.id', $coach->user_id);
    }

    /**
     * `certifications` is a json column that had no cast, so the resource used
     * to hand clients a raw JSON string where every other json column came
     * back decoded.
     */
    public function test_certifications_come_back_as_a_list_not_a_json_string(): void
    {
        $coach = Coach::factory()->create([
            'certifications' => [
                ['name' => 'ISSA CPT', 'issuer' => 'ISSA', 'year' => '2019'],
            ],
        ]);

        $response = $this->showAs($coach)->assertOk();

        $this->assertIsArray($response->json('data.certifications'));
        $response->assertJsonPath('data.certifications.0.name', 'ISSA CPT');
    }

    public function test_a_user_without_a_coach_profile_gets_404(): void
    {
        $coach = Coach::factory()->create();
        $user = $coach->user;
        $user->assignRole(Role::COACH);
        $user->setRelation('coach', null);

        $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->getJson('/api/v1/coaches/me')
            ->assertNotFound();
    }
}
