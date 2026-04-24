<?php

namespace Tests\Feature\Users;

use App\Modules\Users\Models\Certification;
use App\Modules\Users\Services\ObjectStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Users\Concerns\WithCoachUser;
use Tests\TestCase;

class CoachCertificationTest extends TestCase
{
    use WithCoachUser;

    public function test_object_storage_service_uploads_file_and_returns_cdn_url(): void
    {
        Storage::fake('r2_docs');
        config(['app.cdn_base_url' => 'https://cdn.example.com']);

        $file    = UploadedFile::fake()->create('cert.pdf', 500, 'application/pdf');
        $service = new ObjectStorageService();
        $result  = $service->upload($file, 'certifications/1');

        $this->assertArrayHasKey('cdn_url', $result);
        $this->assertArrayHasKey('storage_path', $result);
        $this->assertStringStartsWith('certifications/1/', $result['storage_path']);
        $this->assertStringStartsWith('https://cdn.example.com/', $result['cdn_url']);
    }

    public function test_object_storage_service_deletes_file(): void
    {
        Storage::fake('r2_docs');

        Storage::disk('r2_docs')->put('certifications/1/test.pdf', 'content');

        $service = new ObjectStorageService();
        $service->delete('certifications/1/test.pdf');

        Storage::disk('r2_docs')->assertMissing('certifications/1/test.pdf');
    }

    public function test_coach_can_upload_certification(): void
    {
        [$user, $coach] = $this->createCoachUser();
        Storage::fake('r2_docs');
        config(['app.cdn_base_url' => 'https://cdn.example.com']);

        $file = UploadedFile::fake()->create('cert.pdf', 500, 'application/pdf');

        $response = $this->actingAsCoach($user)
            ->postJson('/api/v1/coaches/me/certifications', [
                'file'   => $file,
                'name'   => 'ISSA Personal Trainer',
                'issuer' => 'ISSA',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'ISSA Personal Trainer')
            ->assertJsonPath('data.issuer', 'ISSA');

        $this->assertDatabaseCount('certifications', 1);
    }

    public function test_certification_upload_rejects_non_pdf_jpg_png(): void
    {
        [$user, $coach] = $this->createCoachUser();
        Storage::fake('r2_docs');

        $file = UploadedFile::fake()->create('malicious.exe', 100, 'application/octet-stream');

        $response = $this->actingAsCoach($user)
            ->postJson('/api/v1/coaches/me/certifications', [
                'file' => $file,
                'name' => 'Fake cert',
            ]);

        $response->assertUnprocessable();
    }

    public function test_certification_upload_rejects_files_over_10mb(): void
    {
        [$user, $coach] = $this->createCoachUser();
        Storage::fake('r2_docs');

        $file = UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf');

        $response = $this->actingAsCoach($user)
            ->postJson('/api/v1/coaches/me/certifications', [
                'file' => $file,
                'name' => 'Big cert',
            ]);

        $response->assertUnprocessable();
    }

    public function test_coach_can_delete_own_certification(): void
    {
        [$user, $coach] = $this->createCoachUser();
        Storage::fake('r2_docs');
        config(['app.cdn_base_url' => 'https://cdn.example.com']);

        Storage::disk('r2_docs')->put('certifications/1/test.pdf', 'content');

        $cert = Certification::create([
            'coach_id'     => $coach->id,
            'name'         => 'Test Cert',
            'file_url'     => 'https://cdn.example.com/certifications/1/test.pdf',
            'storage_path' => 'certifications/1/test.pdf',
        ]);

        $response = $this->actingAsCoach($user)
            ->deleteJson('/api/v1/coaches/me/certifications/' . $cert->id);

        $response->assertNoContent();
        $this->assertDatabaseMissing('certifications', ['id' => $cert->id]);
        Storage::disk('r2_docs')->assertMissing('certifications/1/test.pdf');
    }

    public function test_coach_cannot_delete_another_coachs_certification(): void
    {
        [$user, $coach]   = $this->createCoachUser();
        [$user2, $coach2] = $this->createCoachUser();

        $cert = Certification::create([
            'coach_id'     => $coach2->id,
            'name'         => 'Other cert',
            'file_url'     => 'https://cdn.example.com/cert.pdf',
            'storage_path' => 'certifications/2/cert.pdf',
        ]);

        $response = $this->actingAsCoach($user)
            ->deleteJson('/api/v1/coaches/me/certifications/' . $cert->id);

        $response->assertForbidden();
    }
}
