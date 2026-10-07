<?php

namespace App\Modules\Users\Tests\Feature\Http\Controllers;

use App\Modules\Users\Models\Coach;
use App\Modules\Users\Providers\UsersServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Providers\AuthServiceProvider;
use Tests\TestCase;

class PublicCoachPageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->app->register(UsersServiceProvider::class);
        $this->app->register(AuthServiceProvider::class);
    }

    public function test_shows_published_coach_profile(): void
    {
        $coach = Coach::factory()->create([
            'is_published' => true,
            'slug' => 'mario-rossi',
        ]);

        $response = $this->get('/coach/mario-rossi');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Users::Public/CoachProfile', false)
            ->has('coach')
        );
    }

    public function test_returns_404_for_unpublished_coach(): void
    {
        Coach::factory()->create([
            'is_published' => false,
            'slug' => 'hidden-coach',
        ]);

        $response = $this->get('/coach/hidden-coach');

        $response->assertNotFound();
    }

    public function test_returns_404_for_nonexistent_slug(): void
    {
        $response = $this->get('/coach/nonexistent-slug');

        $response->assertNotFound();
    }

    public function test_loads_coach_relations(): void
    {
        $coach = Coach::factory()->create([
            'is_published' => true,
            'slug' => 'coach-loaded',
        ]);

        $response = $this->get('/coach/coach-loaded');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Users::Public/CoachProfile', false)
            ->has('coach.slug')
            ->has('coach.display_name')
        );
    }

    public function test_public_page_exposes_contact_options(): void
    {
        $coach = Coach::factory()->create([
            'is_published' => true,
            'accepts_direct_message' => true,
            'accepts_direct_booking' => false,
        ]);

        $this->get(route('coach.public.show', $coach->slug))
            ->assertInertia(fn ($page) => $page
                ->where('coach.contact_options.direct_message', true)
                ->where('coach.contact_options.direct_booking', false)
                ->where('coach.contact_options.free_consultation', false)
            );
    }
}
