<?php

namespace App\Modules\Users\Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\ClientAnamnesis;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Providers\UsersServiceProvider;
use App\Modules\Users\Services\EncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Http\Middleware\JwtAuthenticate;
use Modules\Auth\Http\Middleware\RequireVerified;
use Tests\TestCase;

class ClientAnamnesisControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.encryption_key' => 'test-key-for-aes-256-gcm-testing']);
    }

    protected function beforeRefreshingDatabase(): void
    {
        $this->app->register(UsersServiceProvider::class);
    }

    private function makeCoachUser(): array
    {
        $coach = Coach::factory()->create();
        $coachUser = $coach->user;
        $coachUser->assignRole(Role::COACH);
        $coachUser->setRelation('coach', $coach);

        return [$coachUser, $coach];
    }

    private function makeClientAttachedTo(Coach $coach): Client
    {
        $client = Client::factory()->create();
        $coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);

        return $client;
    }

    private function makeAnamnesis(Client $client, string $content): ClientAnamnesis
    {
        $encryption = app(EncryptionService::class);
        $encrypted = $encryption->encrypt($content);

        return ClientAnamnesis::create([
            'client_id' => $client->id,
            'content_encrypted' => $encrypted['ciphertext'],
            'iv' => $encrypted['iv'],
            'tag' => $encrypted['tag'],
        ]);
    }

    public function test_coach_can_get_client_anamnesis(): void
    {
        [$coachUser, $coach] = $this->makeCoachUser();
        $client = $this->makeClientAttachedTo($coach);
        $this->makeAnamnesis($client, 'Patient has no known allergies.');

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coachUser)
            ->getJson("/api/v1/coaches/me/clients/{$client->id}/anamnesis");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['content']])
            ->assertJsonFragment(['content' => 'Patient has no known allergies.']);
    }

    public function test_coach_gets_null_when_no_anamnesis(): void
    {
        [$coachUser, $coach] = $this->makeCoachUser();
        $client = $this->makeClientAttachedTo($coach);

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coachUser)
            ->getJson("/api/v1/coaches/me/clients/{$client->id}/anamnesis");

        $response->assertStatus(200)
            ->assertExactJson(['data' => null]);
    }

    public function test_coach_cannot_get_anamnesis_for_unowned_client(): void
    {
        [$coachUser, $coach] = $this->makeCoachUser();
        $client = Client::factory()->create(); // Not attached to this coach
        $this->makeAnamnesis($client, 'Some content.');

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coachUser)
            ->getJson("/api/v1/coaches/me/clients/{$client->id}/anamnesis");

        $response->assertStatus(403);
    }
}
