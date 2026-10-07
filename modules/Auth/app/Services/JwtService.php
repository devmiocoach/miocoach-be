<?php

namespace Modules\Auth\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;
use stdClass;

class JwtService
{
    private const ALGORITHM = 'RS256';

    private string $privateKey;

    private string $publicKey;

    public function __construct()
    {
        $this->privateKey = $this->normalizeKey(config('auth.modules.jwt.private_key'));
        $this->publicKey = $this->normalizeKey(config('auth.modules.jwt.public_key'));

        if (empty($this->privateKey) || empty($this->publicKey)) {
            throw new \RuntimeException('JWT_PRIVATE_KEY and JWT_PUBLIC_KEY must be configured in .env');
        }
    }

    /**
     * Normalize a PEM key from .env format (handles \n escape sequences).
     */
    private function normalizeKey(string $key): string
    {
        // Convert literal \n in .env to actual newlines
        $key = str_replace('\n', "\n", $key);

        // Ensure proper line endings
        $key = str_replace("\r\n", "\n", $key);

        return trim($key);
    }

    public function generateAccessToken(User $user): string
    {
        return $this->encode([
            'sub' => (string) $user->id,
            'role' => $user->getRoleNames()->first(),
        ], config('auth.modules.jwt.access_ttl'));
    }

    public function generateTempToken(User $user): string
    {
        return $this->encode([
            'sub' => (string) $user->id,
            'two_fa_pending' => true,
        ], config('auth.modules.jwt.temp_ttl'));
    }

    public function generatePasswordResetToken(string $email): string
    {
        return $this->encode([
            'purpose' => 'password_reset',
            'email' => $email,
        ], config('auth.modules.jwt.reset_ttl'));
    }

    public function generateEmailVerifyToken(User $user): string
    {
        return $this->encode([
            'purpose' => 'email_verify',
            'sub' => (string) $user->id,
            'email_hash' => sha1($user->email),
        ], config('auth.modules.jwt.verify_ttl'));
    }

    public function generateInviteToken(int $coachId, string $email): string
    {
        return $this->encode([
            'purpose' => 'invite',
            'coach_id' => $coachId,
            'email' => $email,
            'jti' => Str::uuid()->toString(),
        ], config('auth.modules.jwt.invite_ttl'));
    }

    public function generateBookingCancellationToken(int $bookingId): string
    {
        return $this->encode([
            'purpose' => 'booking_cancellation',
            'booking_id' => $bookingId,
            'jti' => Str::uuid()->toString(),
        ], 30 * 24 * 60 * 60);
    }

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
