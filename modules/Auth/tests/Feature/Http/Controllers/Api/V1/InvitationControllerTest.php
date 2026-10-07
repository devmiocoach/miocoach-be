<?php

namespace Modules\Auth\Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use App\Modules\Users\Actions\SendInvitationAction;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachInvitation;
use App\Modules\Users\Providers\UsersServiceProvider;
use App\Services\InviteTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Auth\Actions\RegisterViaInviteAction;
use Modules\Auth\Services\JwtService;
use Tests\TestCase;

class InvitationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        $publicKey = openssl_pkey_get_details($key)['key'];

        config([
            'auth.modules.jwt.private_key' => $privateKey,
            'auth.modules.jwt.public_key' => $publicKey,
        ]);

        $this->app->forgetInstance(JwtService::class);
        $this->app->forgetInstance(InviteTokenService::class);
        $this->app->forgetInstance(RegisterViaInviteAction::class);
    }

    protected function beforeRefreshingDatabase(): void
    {
        $this->app->register(UsersServiceProvider::class);
    }

    private function validPayload(): array
    {
        return [
            'firstName' => 'Mario',
            'lastName' => 'Rossi',
            'password' => 'Secret1!@#',
            'password_confirmation' => 'Secret1!@#',
            'dateOfBirth' => '1992-05-15',
            'gender' => 'male',
            'heightCm' => 180,
            'objective' => 'Lose weight',
            'fitnessLevel' => 'beginner',
        ];
    }

    private function createInvitation(): array
    {
        /** @var Notification */
        Notification::fake();

        $coach = Coach::factory()->create();
        $invitation = app(SendInvitationAction::class)->handle($coach, 'newuser@example.com');

        return [$coach, $invitation];
    }

    public function test_happy_path_returns_201_with_tokens(): void
    {
        [$coach, $invitation] = $this->createInvitation();

        $response = $this->postJson(
            "/api/v1/auth/accept-invite/{$invitation->token}",
            $this->validPayload()
        );

        $response->assertStatus(201)
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in'])
            ->assertJsonFragment(['token_type' => 'Bearer']);

        $this->assertStringStartsWith('eyJ', $response->json('access_token'));
    }

    public function test_happy_path_sets_refresh_token_cookie(): void
    {
        [$coach, $invitation] = $this->createInvitation();

        $response = $this->postJson(
            "/api/v1/auth/accept-invite/{$invitation->token}",
            $this->validPayload()
        );

        $response->assertStatus(201);
        $this->assertNotNull($response->headers->getCookies());

        $cookies = $response->headers->getCookies();
        $refreshCookieNames = array_map(fn ($c) => $c->getName(), $cookies);
        $this->assertContains('refresh_token', $refreshCookieNames);
    }

    public function test_happy_path_creates_user_with_email_verified(): void
    {
        [$coach, $invitation] = $this->createInvitation();

        $this->postJson(
            "/api/v1/auth/accept-invite/{$invitation->token}",
            $this->validPayload()
        )->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
        ]);

        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_happy_path_creates_client_with_profile_fields(): void
    {
        [$coach, $invitation] = $this->createInvitation();

        $this->postJson(
            "/api/v1/auth/accept-invite/{$invitation->token}",
            $this->validPayload()
        )->assertStatus(201);

        $user = User::where('email', 'newuser@example.com')->first();

        $this->assertDatabaseHas('clients', [
            'user_id' => $user->id,
            'gender' => 'male',
            'objective' => 'Lose weight',
            'fitness_level' => 'beginner',
        ]);
    }

    public function test_happy_path_marks_invitation_as_accepted(): void
    {
        [$coach, $invitation] = $this->createInvitation();

        $this->postJson(
            "/api/v1/auth/accept-invite/{$invitation->token}",
            $this->validPayload()
        )->assertStatus(201);

        $this->assertDatabaseHas('coach_invitations', [
            'id' => $invitation->id,
            'status' => 'accepted',
        ]);

        $this->assertNotNull(
            CoachInvitation::find($invitation->id)->accepted_at
        );
    }

    public function test_happy_path_attaches_client_to_coach(): void
    {
        [$coach, $invitation] = $this->createInvitation();

        $this->postJson(
            "/api/v1/auth/accept-invite/{$invitation->token}",
            $this->validPayload()
        )->assertStatus(201);

        $user = User::where('email', 'newuser@example.com')->first();
        $client = $user->client;

        $this->assertNotNull($client);
        $this->assertTrue($coach->athletes()->where('athlete_id', $client->id)->exists());
    }

    public function test_expired_jwt_returns_422_with_token_error(): void
    {
        $response = $this->postJson(
            '/api/v1/auth/accept-invite/invalid.jwt.token',
            $this->validPayload()
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_invitation_already_used_returns_422(): void
    {
        [$coach, $invitation] = $this->createInvitation();

        $invitation->update(['status' => 'accepted', 'accepted_at' => now()]);

        $response = $this->postJson(
            "/api/v1/auth/accept-invite/{$invitation->token}",
            $this->validPayload()
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_email_already_registered_returns_422_with_message(): void
    {
        [$coach, $invitation] = $this->createInvitation();

        User::factory()->create(['email' => 'newuser@example.com']);

        $response = $this->postJson(
            "/api/v1/auth/accept-invite/{$invitation->token}",
            $this->validPayload()
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);

        $errors = $response->json('errors.token');
        $this->assertContains('Esiste già un account associato a questo invito.', $errors);
    }

    public function test_missing_first_name_returns_422(): void
    {
        [$coach, $invitation] = $this->createInvitation();

        $payload = $this->validPayload();
        unset($payload['firstName']);

        $response = $this->postJson(
            "/api/v1/auth/accept-invite/{$invitation->token}",
            $payload
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['firstName']);
    }

    public function test_backward_compatible_route_also_works(): void
    {
        [$coach, $invitation] = $this->createInvitation();

        $response = $this->postJson(
            "/api/v1/auth/invite/{$invitation->token}/register",
            $this->validPayload()
        );

        $response->assertStatus(201);
    }
}
