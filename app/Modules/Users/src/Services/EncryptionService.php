<?php

namespace App\Modules\Users\Services;

use RuntimeException;

class EncryptionService
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;

    public function encrypt(string $plaintext): array
    {
        $key = $this->deriveKey();
        $iv  = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed: ' . openssl_error_string());
        }

        return [
            'ciphertext' => base64_encode($ciphertext),
            'iv'         => base64_encode($iv),
            'tag'        => base64_encode($tag),
        ];
    }

    public function decrypt(string $ciphertext, string $iv, string $tag): string
    {
        $key = $this->deriveKey();

        $result = openssl_decrypt(
            base64_decode($ciphertext),
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            base64_decode($iv),
            base64_decode($tag)
        );

        if ($result === false) {
            throw new RuntimeException('Decryption failed — data may be corrupt or key mismatch.');
        }

        return $result;
    }

    // All records share the same key material — per-entity key derivation is a future enhancement.
    private function deriveKey(): string
    {
        $raw = config('app.encryption_key', '');
        if ($raw === '') {
            throw new RuntimeException('APP_ENCRYPTION_KEY is not set.');
        }

        return hash('sha256', $raw, true);
    }
}
