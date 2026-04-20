<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use stdClass;

class JwtService
{
    private const ALGORITHM = 'RS256';

    private string $privateKey;
    private string $publicKey;

    public function __construct()
    {
        $this->privateKey = config('auth.jwt.private_key');
        $this->publicKey  = config('auth.jwt.public_key');
    }

    public function generateAccessToken(User $user): string
    {
        return $this->encode([
            'sub'  => (string) $user->id,
            'role' => $user->getRoleNames()->first(),
        ], config('auth.jwt.access_ttl'));
    }

    public function generateTempToken(User $user): string
    {
        return $this->encode([
            'sub'            => (string) $user->id,
            'two_fa_pending' => true,
        ], config('auth.jwt.temp_ttl'));
    }

    public function generatePasswordResetToken(string $email): string
    {
        return $this->encode([
            'purpose' => 'password_reset',
            'email'   => $email,
        ], config('auth.jwt.reset_ttl'));
    }

    public function generateEmailVerifyToken(User $user): string
    {
        return $this->encode([
            'purpose'    => 'email_verify',
            'sub'        => (string) $user->id,
            'email_hash' => sha1($user->email),
        ], config('auth.jwt.verify_ttl'));
    }

    /**
     * @throws \Firebase\JWT\ExpiredException
     * @throws \Firebase\JWT\SignatureInvalidException
     * @throws \UnexpectedValueException
     */
    public function decode(string $token): stdClass
    {
        return JWT::decode($token, new Key($this->publicKey, self::ALGORITHM));
    }

    private function encode(array $claims, int $ttl): string
    {
        $now = time();

        return JWT::encode(
            array_merge([
                'iss' => config('app.url'),
                'iat' => $now,
                'exp' => $now + $ttl,
            ], $claims),
            $this->privateKey,
            self::ALGORITHM,
        );
    }
}
