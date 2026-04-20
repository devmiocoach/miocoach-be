<?php

namespace Tests\Feature\Users;

use App\Modules\Users\Services\EncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachClientAnamnesisTest extends TestCase
{
    use RefreshDatabase;

    public function test_encryption_service_encrypts_and_decrypts(): void
    {
        config(['app.encryption_key' => 'test-secret-key-for-unit-tests-only']);

        $service   = new EncryptionService();
        $plaintext = 'Patient has knee pain on left side.';

        $encrypted = $service->encrypt($plaintext);

        $this->assertArrayHasKey('ciphertext', $encrypted);
        $this->assertArrayHasKey('iv', $encrypted);
        $this->assertArrayHasKey('tag', $encrypted);
        $this->assertNotEquals($plaintext, $encrypted['ciphertext']);

        $decrypted = $service->decrypt(
            $encrypted['ciphertext'],
            $encrypted['iv'],
            $encrypted['tag']
        );

        $this->assertEquals($plaintext, $decrypted);
    }

    public function test_each_encryption_produces_different_iv(): void
    {
        config(['app.encryption_key' => 'test-secret-key-for-unit-tests-only']);

        $service = new EncryptionService();
        $a       = $service->encrypt('same content');
        $b       = $service->encrypt('same content');

        $this->assertNotEquals($a['iv'], $b['iv']);
    }
}
