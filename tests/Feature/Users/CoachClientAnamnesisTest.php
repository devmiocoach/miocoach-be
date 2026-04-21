<?php

namespace Tests\Feature\Users;

use App\Modules\Users\Services\EncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Users\Concerns\WithCoachUser;
use Tests\TestCase;

class CoachClientAnamnesisTest extends TestCase
{
    use RefreshDatabase, WithCoachUser;

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

    public function test_coach_can_update_client_anamnesis(): void
    {
        [$coachUser, $coach] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach);

        config(['app.encryption_key' => 'test-secret-key-for-unit-tests-only']);

        $response = $this->actingAsCoach($coachUser)
            ->putJson("/api/v1/coaches/me/clients/{$client->id}/anamnesis", [
                'content' => 'Paziente con lombalgia cronica.',
            ]);

        $response->assertOk()->assertJsonPath('data.message', 'Anamnesi aggiornata.');

        $record = \App\Modules\Users\Models\ClientAnamnesis::where('client_id', $client->id)->first();
        $this->assertNotNull($record);
        $this->assertNotEquals('Paziente con lombalgia cronica.', $record->content_encrypted);
        $this->assertNotEmpty($record->iv);
        $this->assertNotEmpty($record->tag);
    }

    public function test_anamnesis_is_decrypted_in_client_detail(): void
    {
        [$coachUser, $coach] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach);

        config(['app.encryption_key' => 'test-secret-key-for-unit-tests-only']);

        $this->actingAsCoach($coachUser)
            ->putJson("/api/v1/coaches/me/clients/{$client->id}/anamnesis", [
                'content' => 'Allergia ai FANS.',
            ]);

        $response = $this->actingAsCoach($coachUser)
            ->getJson("/api/v1/coaches/me/clients/{$client->id}");

        $response->assertOk()->assertJsonPath('data.anamnesis', 'Allergia ai FANS.');
    }

    public function test_anamnesis_update_is_overwrite_not_append(): void
    {
        [$coachUser, $coach] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach);

        config(['app.encryption_key' => 'test-secret-key-for-unit-tests-only']);

        $this->actingAsCoach($coachUser)->putJson("/api/v1/coaches/me/clients/{$client->id}/anamnesis", ['content' => 'Version 1']);
        $this->actingAsCoach($coachUser)->putJson("/api/v1/coaches/me/clients/{$client->id}/anamnesis", ['content' => 'Version 2']);

        $this->assertDatabaseCount('client_anamnesis', 1);

        $response = $this->actingAsCoach($coachUser)->getJson("/api/v1/coaches/me/clients/{$client->id}");
        $response->assertJsonPath('data.anamnesis', 'Version 2');
    }
}
