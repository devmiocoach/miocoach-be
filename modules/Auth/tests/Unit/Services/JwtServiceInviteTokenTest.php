<?php

namespace Modules\Auth\Tests\Unit\Services;

use Modules\Auth\Services\JwtService;
use Tests\TestCase;

class JwtServiceInviteTokenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        $publicKey = openssl_pkey_get_details($key)['key'];

        // Forget any cached instance so the new config is picked up
        $this->app->forgetInstance(JwtService::class);

        config([
            'auth.modules.jwt.private_key' => $privateKey,
            'auth.modules.jwt.public_key' => $publicKey,
        ]);
    }

    public function test_generates_invite_token_with_correct_claims(): void
    {
        $service = app(JwtService::class);
        $token = $service->generateInviteToken(42, 'user@example.com');

        $this->assertIsString($token);
        $this->assertSame(2, substr_count($token, '.'), 'JWT must have 3 parts (header.payload.signature)');

        $payload = $service->decode($token);

        $this->assertSame('invite', $payload->purpose);
        $this->assertSame(42, $payload->coach_id);
        $this->assertSame('user@example.com', $payload->email);
    }

    public function test_invite_token_expires_in_seven_days(): void
    {
        $service = app(JwtService::class);
        $token = $service->generateInviteToken(1, 'test@test.com');
        $payload = $service->decode($token);

        $expectedTtl = config('auth.modules.jwt.invite_ttl');
        $this->assertEqualsWithDelta(time() + $expectedTtl, $payload->exp, 60);
    }
}
