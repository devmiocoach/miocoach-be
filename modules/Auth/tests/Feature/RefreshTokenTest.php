<?php

namespace Modules\Auth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\RefreshToken;
use Modules\Auth\Services\RefreshTokenService;
use Tests\TestCase;

class RefreshTokenTest extends TestCase
{
    use RefreshDatabase;

    private function issueRefreshToken(?string $deviceName = 'iPhone'): array
    {
        $user = $this->createUser();
        $plaintext = app(RefreshTokenService::class)->create($user, $deviceName);

        return [$user, $plaintext];
    }

    public function test_refresh_with_token_in_body_returns_new_token_pair(): void
    {
        [$user, $refreshToken] = $this->issueRefreshToken();

        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'access_token',
            'refresh_token',
            'token_type',
            'expires_in',
            'user' => ['id', 'name', 'email', 'role'],
        ]);
        $response->assertJsonPath('token_type', 'Bearer');
        $response->assertJsonPath('user.id', $user->id);

        // Rotation: a brand new refresh token is returned.
        $this->assertNotSame($refreshToken, $response->json('refresh_token'));
    }

    public function test_refresh_works_without_a_valid_access_token(): void
    {
        // The whole point of the fix: no Authorization header at all.
        [, $refreshToken] = $this->issueRefreshToken();

        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('access_token'));
    }

    public function test_refresh_rotates_and_revokes_the_old_token(): void
    {
        [, $refreshToken] = $this->issueRefreshToken();

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refreshToken])
            ->assertStatus(200);

        // Old token is revoked in storage…
        $old = RefreshToken::where('token_hash', hash('sha256', $refreshToken))->first();
        $this->assertNotNull($old->revoked_at);

        // …and can no longer be used.
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refreshToken])
            ->assertStatus(422);
    }

    public function test_refresh_with_invalid_token_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => 'not-a-real-token',
        ]);

        $response->assertStatus(422);
    }

    public function test_refresh_without_token_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/auth/refresh', []);

        $response->assertStatus(422);
    }
}
