<?php

namespace App\Modules\Users\Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\ClientFile;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Providers\UsersServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Auth\Http\Middleware\JwtAuthenticate;
use Modules\Auth\Http\Middleware\RequireVerified;
use Tests\TestCase;

class ClientFileControllerTest extends TestCase
{
    use RefreshDatabase;

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

    private function makeFile(Client $client, Coach $coach, string $storageKey = 'files/test.pdf'): ClientFile
    {
        $file = new ClientFile;
        $file->client_id = $client->id;
        $file->coach_id = $coach->id;
        $file->name = 'test.pdf';
        $file->mime_type = 'application/pdf';
        $file->size = 1024;
        $file->storage_key = $storageKey;
        $file->save();

        return $file;
    }

    public function test_coach_can_list_client_files(): void
    {
        [$coachUser, $coach] = $this->makeCoachUser();
        $client = $this->makeClientAttachedTo($coach);
        $this->makeFile($client, $coach, 'files/file1.pdf');
        $this->makeFile($client, $coach, 'files/file2.pdf');

        // File from another coach should not appear
        $otherCoach = Coach::factory()->create();
        $otherCoach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);
        $this->makeFile($client, $otherCoach, 'files/other.pdf');

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coachUser)
            ->getJson("/api/v1/coaches/me/clients/{$client->id}/files");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'name', 'mime_type', 'size', 'created_at']]])
            ->assertJsonCount(2, 'data');
    }

    public function test_coach_cannot_list_files_for_unowned_client(): void
    {
        [$coachUser, $coach] = $this->makeCoachUser();
        $client = Client::factory()->create(); // Not attached to this coach

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coachUser)
            ->getJson("/api/v1/coaches/me/clients/{$client->id}/files");

        $response->assertStatus(403);
    }

    public function test_coach_can_delete_own_file(): void
    {
        Storage::fake('r2_docs');

        [$coachUser, $coach] = $this->makeCoachUser();
        $client = $this->makeClientAttachedTo($coach);
        $storageKey = 'files/delete-me.pdf';
        $file = $this->makeFile($client, $coach, $storageKey);

        Storage::disk('r2_docs')->put($storageKey, 'fake content');
        Storage::disk('r2_docs')->assertExists($storageKey);

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coachUser)
            ->deleteJson("/api/v1/coaches/me/clients/{$client->id}/files/{$file->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('client_files', ['id' => $file->id]);
        Storage::disk('r2_docs')->assertMissing($storageKey);
    }

    public function test_coach_cannot_delete_another_coachs_file(): void
    {
        [$coachUser, $coach] = $this->makeCoachUser();
        $client = $this->makeClientAttachedTo($coach);

        $otherCoach = Coach::factory()->create();
        $otherCoach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);
        $otherFile = $this->makeFile($client, $otherCoach, 'files/other.pdf');

        $response = $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coachUser)
            ->deleteJson("/api/v1/coaches/me/clients/{$client->id}/files/{$otherFile->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('client_files', ['id' => $otherFile->id]);
    }
}
