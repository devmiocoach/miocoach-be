<?php

namespace App\Modules\Users\Tests\Feature\Http\Controllers\Api\V1;

use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachAvailability;
use App\Modules\Users\Models\Review;
use App\Modules\Users\Models\Specialization;
use App\Modules\Users\Providers\UsersServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Providers\AuthServiceProvider;
use Tests\TestCase;

class CoachListingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->app->register(UsersServiceProvider::class);
        $this->app->register(AuthServiceProvider::class);
    }

    public function test_returns_only_published_coaches(): void
    {
        Coach::factory()->create(['is_published' => true]);
        Coach::factory()->create(['is_published' => false]);

        $response = $this->getJson('/api/v1/coaches');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_filters_by_city(): void
    {
        Coach::factory()->create(['city' => 'Milano', 'is_published' => true]);
        Coach::factory()->create(['city' => 'Roma', 'is_published' => true]);

        $response = $this->getJson('/api/v1/coaches?city=Milano');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.city', 'Milano');
    }

    public function test_filters_by_mode_array(): void
    {
        Coach::factory()->create(['mode' => 'online', 'is_published' => true]);
        Coach::factory()->create(['mode' => 'in_person', 'is_published' => true]);
        Coach::factory()->create(['mode' => 'hybrid', 'is_published' => true]);

        $response = $this->getJson('/api/v1/coaches?mode[]=online&mode[]=hybrid');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_filters_by_price_range_in_euros_on_single_session_price(): void
    {
        Coach::factory()->create(['single_session_price_cents' => 3000, 'is_published' => true]);
        $mid = Coach::factory()->create(['single_session_price_cents' => 8000, 'is_published' => true]);
        Coach::factory()->create(['single_session_price_cents' => 15000, 'is_published' => true]);

        $response = $this->getJson('/api/v1/coaches?price_min=50&price_max=100');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mid->id);
    }

    public function test_filters_by_gender(): void
    {
        Coach::factory()->create(['gender' => 'male', 'is_published' => true]);
        Coach::factory()->create(['gender' => 'female', 'is_published' => true]);
        Coach::factory()->create(['gender' => null, 'is_published' => true]);

        $response = $this->getJson('/api/v1/coaches?gender=female');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_filters_by_available_days(): void
    {
        $coach = Coach::factory()->create(['is_published' => true]);
        CoachAvailability::factory()->create([
            'coach_id' => $coach->id,
            'day_of_week' => 1,
            'is_recurring' => true,
        ]);

        Coach::factory()->create(['is_published' => true]);

        $response = $this->getJson('/api/v1/coaches?available_days[]=1');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_sorts_by_single_session_price_asc(): void
    {
        Coach::factory()->create(['single_session_price_cents' => 8000, 'is_published' => true]);
        $cheapest = Coach::factory()->create(['single_session_price_cents' => 3000, 'is_published' => true]);

        $response = $this->getJson('/api/v1/coaches?sort=price_asc');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $cheapest->id);
    }

    public function test_filters_by_specialization_slug_through_pivot(): void
    {
        $coachA = Coach::factory()->create(['is_published' => true]);
        $coachB = Coach::factory()->create(['is_published' => true]);
        $nutrition = Specialization::factory()->create(['slug' => 'nutrition']);
        $strength = Specialization::factory()->create(['slug' => 'strength']);
        $coachA->specializationRecords()->attach([$nutrition->id, $strength->id]);
        $coachB->specializationRecords()->attach([$nutrition->id]);

        $response = $this->getJson('/api/v1/coaches?specialization[]=strength');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $coachA->id);

        // OR semantics: any of the selected specializations
        $responseOr = $this->getJson('/api/v1/coaches?specialization[]=nutrition&specialization[]=strength');

        $responseOr->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_filters_by_minimum_rating_on_published_reviews(): void
    {
        $coachA = Coach::factory()->create(['is_published' => true]);
        $coachB = Coach::factory()->create(['is_published' => true]);
        Review::factory()->create([
            'coach_id' => $coachA->id,
            'rating' => 5,
            'published_at' => now(),
        ]);
        Review::factory()->create([
            'coach_id' => $coachB->id,
            'rating' => 3,
            'published_at' => now(),
        ]);
        Review::factory()->create([
            'coach_id' => $coachB->id,
            'rating' => 5,
            'published_at' => null,
        ]);

        $response = $this->getJson('/api/v1/coaches?rating_min=4.5');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $coachA->id);
        $response->assertJsonPath('meta.total', 1);
    }

    public function test_search_term_matches_display_name_and_specialization_name(): void
    {
        $coachA = Coach::factory()->create([
            'slug' => 'marco-bianchi',
            'display_name' => 'Marco Bianchi',
            'tagline' => 'Personal trainer certificato',
            'bio' => 'Esperto di preparazione atletica.',
            'is_published' => true,
        ]);
        $specialization = Specialization::factory()->create(['slug' => 'nutrition', 'name' => 'Nutrizione Sportiva']);
        $coachB = Coach::factory()->create(['display_name' => 'Luca Verdi', 'is_published' => true]);
        $coachB->specializationRecords()->attach([$specialization->id]);

        $responseA = $this->getJson('/api/v1/coaches?search='.rawurlencode('Marco'));

        $responseA->assertOk();
        $this->assertEqualsCanonicalizing([$coachA->id], collect($responseA->json('data'))->pluck('id')->all());

        $responseB = $this->getJson('/api/v1/coaches?search='.rawurlencode('Nutrizione'));

        $responseB->assertOk();
        $this->assertEqualsCanonicalizing([$coachB->id], collect($responseB->json('data'))->pluck('id')->all());
    }

    public function test_rejects_invalid_filter_values(): void
    {
        Coach::factory()->count(2)->create(['is_published' => true]);

        $this->getJson('/api/v1/coaches?sort=drop_table')
            ->assertUnprocessable();

        $this->getJson('/api/v1/coaches?rating_min=9')
            ->assertUnprocessable();
    }

    public function test_listing_item_exposes_price_and_specializations_from_onboarding(): void
    {
        $nutrition = Specialization::factory()->create(['name' => 'Nutrizione', 'slug' => 'nutrition']);
        $coach = Coach::factory()->create(['is_published' => true, 'single_session_price_cents' => 4500]);
        $coach->specializationRecords()->attach([$nutrition->id]);

        $this->getJson('/api/v1/coaches')
            ->assertOk()
            ->assertJsonPath('data.0.price_from_cents', 4500)
            ->assertJsonPath('data.0.specializations.0.slug', 'nutrition')
            ->assertJsonPath('data.0.specializations.0.name', 'Nutrizione');
    }

    public function test_includes_rating_and_reviews_count(): void
    {
        $coach = Coach::factory()->create(['is_published' => true]);
        Review::factory()->count(3)->create([
            'coach_id' => $coach->id,
            'rating' => 4,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/coaches');

        $response->assertOk();
        $this->assertEquals(4.0, $response->json('data.0.rating'));
        $this->assertEquals(3, $response->json('data.0.reviews_count'));
    }
}
