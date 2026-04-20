<?php

namespace Tests\Feature\Users;

use App\Modules\Users\Services\GeocodingService;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Users\Concerns\WithCoachUser;
use Tests\TestCase;

class CoachProfileTest extends TestCase
{
    use WithCoachUser;

    public function test_coach_can_get_own_profile(): void
    {
        [$user, $coach] = $this->createCoachUser([
            'bio'     => 'Professional coach',
            'city'    => 'Milano',
            'mode'    => 'online',
            'tagline' => 'Your best coach',
        ]);

        $response = $this->actingAsCoach($user)
            ->getJson('/api/v1/coaches/me');

        $response->assertOk()
            ->assertJsonPath('data.id', $coach->id)
            ->assertJsonPath('data.bio', 'Professional coach')
            ->assertJsonPath('data.tagline', 'Your best coach')
            ->assertJsonPath('data.mode', 'online');
    }

    public function test_geocoding_service_returns_lat_lng_for_city(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '45.4654219', 'lon' => '9.1859243'],
            ], 200),
        ]);

        $service = new GeocodingService();
        $result  = $service->geocode('Milano');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('lat', $result);
        $this->assertArrayHasKey('lng', $result);
        $this->assertEquals('45.4654219', $result['lat']);
        $this->assertEquals('9.1859243', $result['lng']);
    }

    public function test_geocoding_service_returns_null_when_city_not_found(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
        ]);

        $service = new GeocodingService();
        $result  = $service->geocode('CittàInesistente12345');

        $this->assertNull($result);
    }

    public function test_coach_can_update_profile_with_new_fields(): void
    {
        [$user, $coach] = $this->createCoachUser();

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '45.4654219', 'lon' => '9.1859243'],
            ], 200),
        ]);

        $response = $this->actingAsCoach($user)
            ->patchJson('/api/v1/coaches/me', [
                'tagline'                   => 'Top coach Milano',
                'mode'                      => 'in_person',
                'languages'                 => ['it', 'en'],
                'city'                      => 'Milano',
                'price_per_session'         => 60.00,
                'cancellation_window_hours' => 24,
                'instagram_url'             => 'https://instagram.com/coach',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.tagline', 'Top coach Milano')
            ->assertJsonPath('data.mode', 'in_person')
            ->assertJsonPath('data.price_per_session', '60.00')
            ->assertJsonPath('data.lat', 45.4654219)
            ->assertJsonPath('data.lng', 9.1859243);
    }

    public function test_profile_update_logs_audit_event(): void
    {
        [$user, $coach] = $this->createCoachUser();

        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([], 200)]);

        $this->actingAsCoach($user)
            ->patchJson('/api/v1/coaches/me', ['bio' => 'Updated bio']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event'   => 'PROFILE_UPDATED',
        ]);
    }

    public function test_profile_update_generates_slug_if_missing(): void
    {
        [$user, $coach] = $this->createCoachUser();
        $coach->slug = '';
        $coach->saveQuietly();

        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([], 200)]);

        $this->actingAsCoach($user)
            ->patchJson('/api/v1/coaches/me', [
                'display_name' => 'Mario Bianchi',
                'city'         => 'Roma',
            ]);

        $coach->refresh();
        $this->assertEquals('mario-bianchi-roma', $coach->slug);
    }
}
