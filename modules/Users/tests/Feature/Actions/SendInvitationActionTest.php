<?php

namespace App\Modules\Users\Tests\Feature\Actions;

use App\Modules\Users\Actions\SendInvitationAction;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Providers\UsersServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Services\JwtService;
use Tests\TestCase;

class SendInvitationActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        $publicKey = openssl_pkey_get_details($key)['key'];

        $this->app->forgetInstance(JwtService::class);
        config([
            'auth.modules.jwt.private_key' => $privateKey,
            'auth.modules.jwt.public_key' => $publicKey,
        ]);
    }

    protected function beforeRefreshingDatabase(): void
    {
        $this->app->register(UsersServiceProvider::class);
    }

    public function test_creates_invitation_with_jwt_token(): void
    {
        Notification::fake();

        $coach = Coach::factory()->create();

        $invitation = app(SendInvitationAction::class)->handle($coach, 'athlete@example.com');

        $this->assertDatabaseHas('coach_invitations', [
            'coach_id' => $coach->id,
            'email' => 'athlete@example.com',
            'status' => 'pending',
        ]);

        $token = $invitation->token;
        $this->assertStringStartsWith('eyJ', $token);
        // JWT has exactly 3 dot-separated segments
        $this->assertSame(2, substr_count($token, '.'));
    }

    public function test_token_is_valid_jwt_with_three_segments(): void
    {
        Notification::fake();

        $coach = Coach::factory()->create();
        $invitation = app(SendInvitationAction::class)->handle($coach, 'athlete2@example.com');

        $segments = explode('.', $invitation->token);
        $this->assertCount(3, $segments);

        foreach ($segments as $segment) {
            $this->assertNotEmpty($segment);
        }
    }

    public function test_revokes_prior_pending_invite_for_same_email(): void
    {
        Notification::fake();

        $coach = Coach::factory()->create();

        $first = app(SendInvitationAction::class)->handle($coach, 'athlete@example.com');
        app(SendInvitationAction::class)->handle($coach, 'athlete@example.com');

        $this->assertDatabaseHas('coach_invitations', [
            'id' => $first->id,
            'status' => 'revoked',
        ]);
    }

    public function test_throws_when_max_pending_invitations_reached(): void
    {
        Notification::fake();

        $coach = Coach::factory()->create();

        for ($i = 0; $i < 20; $i++) {
            app(SendInvitationAction::class)->handle($coach, "athlete{$i}@example.com");
        }

        $this->expectException(ValidationException::class);
        app(SendInvitationAction::class)->handle($coach, 'extra@example.com');
    }

    public function test_normalises_email_to_lowercase(): void
    {
        Notification::fake();

        $coach = Coach::factory()->create();
        $invitation = app(SendInvitationAction::class)->handle($coach, 'Athlete@Example.COM');

        $this->assertSame('athlete@example.com', $invitation->email);
        $this->assertDatabaseHas('coach_invitations', [
            'email' => 'athlete@example.com',
        ]);
    }
}
