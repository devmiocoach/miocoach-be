<?php

namespace Tests\Unit\Services;

use App\Services\InviteTokenService;
use Modules\Auth\Services\JwtService;
use PHPUnit\Framework\TestCase;
use stdClass;

class InviteTokenServiceTest extends TestCase
{
    public function test_generate_delegates_to_jwt_service(): void
    {
        $mockJwt = $this->createMock(JwtService::class);
        $mockJwt->expects($this->once())
            ->method('generateInviteToken')
            ->with(42, 'user@example.com')
            ->willReturn('header.payload.signature');

        $service = new InviteTokenService($mockJwt);

        $this->assertSame('header.payload.signature', $service->generate(42, 'user@example.com'));
    }

    public function test_decode_returns_payload_for_valid_invite_token(): void
    {
        $mockPayload = new stdClass;
        $mockPayload->purpose = 'invite';
        $mockPayload->coach_id = 42;
        $mockPayload->email = 'user@example.com';

        $mockJwt = $this->createMock(JwtService::class);
        $mockJwt->method('decode')->willReturn($mockPayload);

        $service = new InviteTokenService($mockJwt);
        $result = $service->decode('any.jwt.token');

        $this->assertSame(42, $result->coach_id);
        $this->assertSame('user@example.com', $result->email);
    }

    public function test_decode_throws_when_purpose_is_not_invite(): void
    {
        $mockPayload = new stdClass;
        $mockPayload->purpose = 'password_reset';

        $mockJwt = $this->createMock(JwtService::class);
        $mockJwt->method('decode')->willReturn($mockPayload);

        $service = new InviteTokenService($mockJwt);

        $this->expectException(\InvalidArgumentException::class);
        $service->decode('some.jwt.token');
    }

    public function test_decode_throws_when_purpose_is_missing(): void
    {
        $mockJwt = $this->createMock(JwtService::class);
        $mockJwt->method('decode')->willReturn(new stdClass);

        $service = new InviteTokenService($mockJwt);

        $this->expectException(\InvalidArgumentException::class);
        $service->decode('some.jwt.token');
    }

    public function test_decode_wraps_jwt_exception_as_invalid_argument_exception(): void
    {
        $mockJwt = $this->createMock(JwtService::class);
        $mockJwt->method('decode')->willThrowException(new \RuntimeException('Token expired'));

        $service = new InviteTokenService($mockJwt);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Il link di invito non è valido o è scaduto.');
        $service->decode('expired.jwt.token');
    }
}
