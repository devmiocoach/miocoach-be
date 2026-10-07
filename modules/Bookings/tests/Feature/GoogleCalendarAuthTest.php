<?php

namespace Modules\Bookings\Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Modules\Auth\Http\Middleware\JwtAuthenticate;
use Modules\Auth\Http\Middleware\RequireVerified;
use Tests\TestCase;

class GoogleCalendarAuthTest extends TestCase
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

    public function test_status_returns_not_connected_by_default(): void
    {
        [$user] = $this->actingAsCoach();

        $response = $this
            ->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->getJson('/api/v1/coaches/me/calendar/status');

        $response->assertOk()
            ->assertJson(['connected' => false, 'calendar_id' => 'primary']);
    }

    public function test_status_returns_connected_when_tokens_present(): void
    {
        [$user, $coach] = $this->actingAsCoach();
        $coach->google_calendar_access_token = Crypt::encryptString('fake_access_token');
        $coach->save();

        $response = $this
            ->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->getJson('/api/v1/coaches/me/calendar/status');

        $response->assertOk()
            ->assertJson(['connected' => true]);
    }

    public function test_get_auth_url_requires_coach_profile(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::COACH);

        $response = $this
            ->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->getJson('/api/v1/coaches/me/calendar/auth');

        $response->assertStatus(403);
    }

    public function test_get_auth_url_stores_state_in_cache(): void
    {
        config([
            'bookings.google_calendar.client_id' => 'fake_client_id',
            'bookings.google_calendar.client_secret' => 'fake_secret',
            'bookings.google_calendar.redirect_uri' => 'http://localhost/callback',
        ]);

        [$user] = $this->actingAsCoach();

        $response = $this
            ->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->getJson('/api/v1/coaches/me/calendar/auth');

        $response->assertOk()
            ->assertJsonStructure(['auth_url']);

        $authUrl = $response->json('auth_url');
        $this->assertStringContainsString('accounts.google.com', $authUrl);

        preg_match('/state=([^&]+)/', $authUrl, $matches);
        $state = urldecode($matches[1] ?? '');
        $this->assertNotEmpty($state);
        $this->assertTrue(Cache::has("gcal_oauth_state:{$state}"));
    }

    public function test_callback_redirects_to_error_on_missing_params(): void
    {
        $response = $this->get('/api/v1/coaches/calendar/callback');

        $response->assertRedirect();
        $this->assertStringContainsString('calendar=error', $response->headers->get('Location'));
    }

    public function test_callback_redirects_to_error_on_invalid_state(): void
    {
        $response = $this->get('/api/v1/coaches/calendar/callback?code=abc&state=invalid_state');

        $response->assertRedirect();
        $this->assertStringContainsString('invalid_state', $response->headers->get('Location'));
    }

    public function test_disconnect_clears_tokens(): void
    {
        [$user, $coach] = $this->actingAsCoach();
        $coach->google_calendar_access_token = Crypt::encryptString('fake_access_token');
        $coach->google_calendar_refresh_token = Crypt::encryptString('fake_refresh_token');
        $coach->save();

        $response = $this
            ->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->deleteJson('/api/v1/coaches/me/calendar/disconnect');

        $response->assertOk();

        $coach->refresh();
        $this->assertNull($coach->google_calendar_access_token);
        $this->assertNull($coach->google_calendar_refresh_token);
        $this->assertNull($coach->google_calendar_token_expires_at);
    }

    public function test_disconnect_when_not_connected_returns_ok(): void
    {
        [$user] = $this->actingAsCoach();

        $response = $this
            ->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($user)
            ->deleteJson('/api/v1/coaches/me/calendar/disconnect');

        $response->assertOk();
    }
}
