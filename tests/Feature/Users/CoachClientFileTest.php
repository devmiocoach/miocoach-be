<?php

namespace Tests\Feature\Users;

use App\Modules\Users\Models\ClientFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Users\Concerns\WithCoachUser;
use Tests\TestCase;

class CoachClientFileTest extends TestCase
{
    use WithCoachUser;

    public function test_coach_can_upload_file_for_client(): void
    {
        Storage::fake('r2_docs');

        [$coachUser, $coach] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach);

        $file = UploadedFile::fake()->create('report.pdf', 500, 'application/pdf');

        $response = $this->actingAsCoach($coachUser)
            ->postJson("/api/v1/coaches/me/clients/{$client->id}/files", [
                'file' => $file,
                'name' => 'Report Visita Medica',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Report Visita Medica')
            ->assertJsonPath('data.mime_type', 'application/pdf');

        $this->assertDatabaseHas('client_files', [
            'client_id' => $client->id,
            'coach_id'  => $coach->id,
            'name'      => 'Report Visita Medica',
        ]);
    }

    public function test_file_upload_rejects_unsupported_type(): void
    {
        Storage::fake('r2_docs');

        [$coachUser, $coach] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach);

        $file = UploadedFile::fake()->create('script.exe', 100, 'application/octet-stream');

        $this->actingAsCoach($coachUser)
            ->postJson("/api/v1/coaches/me/clients/{$client->id}/files", [
                'file' => $file,
            ])
            ->assertUnprocessable();
    }

    public function test_coach_can_get_presigned_download_url(): void
    {
        Storage::fake('r2_docs');

        [$coachUser, $coach] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach);

        $clientFile = new ClientFile();
        $clientFile->coach_id    = $coach->id;
        $clientFile->client_id   = $client->id;
        $clientFile->storage_key = 'client-files/1/1/test-uuid.pdf';
        $clientFile->name        = 'Test File';
        $clientFile->mime_type   = 'application/pdf';
        $clientFile->size        = 1024;
        $clientFile->save();

        $mockDisk = \Mockery::mock();
        $mockDisk->shouldReceive('temporaryUrl')
            ->once()
            ->andReturn('https://r2.example.com/signed?token=abc');

        Storage::shouldReceive('disk')
            ->with('r2_docs')
            ->andReturn($mockDisk);

        $response = $this->actingAsCoach($coachUser)
            ->getJson("/api/v1/coaches/me/clients/{$client->id}/files/{$clientFile->id}/download");

        $response->assertOk()
            ->assertJsonPath('data.url', 'https://r2.example.com/signed?token=abc');
    }
}
