<?php

namespace App\Services;

use Modules\Auth\Services\JwtService;
use stdClass;

class InviteTokenService
{
    public function __construct(private readonly JwtService $jwt) {}

    public function generate(int $coachId, string $email): string
    {
        return $this->jwt->generateInviteToken($coachId, $email);
    }

    /** @throws \InvalidArgumentException on expired, invalid, or non-invite tokens */
    public function decode(string $token): stdClass
    {
        try {
            $payload = $this->jwt->decode($token);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Il link di invito non è valido o è scaduto.', 0, $e);
        }

        if (($payload->purpose ?? null) !== 'invite') {
            throw new \InvalidArgumentException('Token is not an invite token.');
        }

        return $payload;
    }
}
