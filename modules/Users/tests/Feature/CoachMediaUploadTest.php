<?php

namespace Modules\Users\Tests\Feature;

use App\Enums\Role;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CoachMediaUploadTest extends TestCase
{
    use RefreshDatabase;

    private function makeCoachUser(): array
    {
        $coach = Coach::factory()->create();
        $coachUser = $coach->user;
        $coachUser->assignRole(Role::COACH);

        return [$coachUser, $coach];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_upload_profile_photo_within_2mb_succeeds(): void
    {
        [$user, $coach] = $this->makeCoachUser();

        $file = UploadedFile::fake()->image('profile.jpg', 800, 800)->size(1024);

        $response = $this->actingAs($user)
            ->postJson(route('coach.onboarding.upload-photo'), [
                'photo' => $file,
                'type' => 'profile',
            ]);

        $response->assertOk()
            ->assertJsonStructure(['url']);

        $this->assertCount(1, $coach->fresh()->getMedia('profile_photo'));
    }

    public function test_upload_profile_photo_over_2mb_fails(): void
    {
        [$user] = $this->makeCoachUser();

        $file = UploadedFile::fake()->image('big.jpg', 4000, 4000)->size(3000);

        $response = $this->actingAs($user)
            ->postJson(route('coach.onboarding.upload-photo'), [
                'photo' => $file,
                'type' => 'profile',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('photo');
    }

    public function test_upload_cover_photo_succeeds(): void
    {
        [$user, $coach] = $this->makeCoachUser();

        $file = UploadedFile::fake()->image('cover.jpg', 1200, 400)->size(1500);

        $response = $this->actingAs($user)
            ->postJson(route('coach.onboarding.upload-photo'), [
                'photo' => $file,
                'type' => 'cover',
            ]);

        $response->assertOk()
            ->assertJsonStructure(['url']);

        $this->assertCount(1, $coach->fresh()->getMedia('cover_photo'));
    }

    public function test_upload_gallery_photo_succeeds(): void
    {
        [$user, $coach] = $this->makeCoachUser();

        $file = UploadedFile::fake()->image('gallery.jpg', 600, 600)->size(500);

        $response = $this->actingAs($user)
            ->postJson(route('coach.onboarding.upload-gallery-photo'), [
                'photo' => $file,
            ]);

        $response->assertOk()
            ->assertJsonStructure(['url', 'photos']);

        $this->assertCount(1, $coach->fresh()->getMedia('gallery'));
    }

    public function test_upload_gallery_photo_respects_max_6_limit(): void
    {
        [$user, $coach] = $this->makeCoachUser();

        for ($i = 0; $i < 6; $i++) {
            $file = UploadedFile::fake()->image("gallery_{$i}.jpg", 400, 400)->size(500);
            $this->actingAs($user)
                ->postJson(route('coach.onboarding.upload-gallery-photo'), ['photo' => $file])
                ->assertOk();
        }

        $file = UploadedFile::fake()->image('gallery_7.jpg', 400, 400)->size(500);
        $response = $this->actingAs($user)
            ->postJson(route('coach.onboarding.upload-gallery-photo'), ['photo' => $file]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'Limite massimo di 6 foto raggiunto.']);
    }

    public function test_upload_gallery_photo_over_2mb_fails(): void
    {
        [$user] = $this->makeCoachUser();

        $file = UploadedFile::fake()->image('big.jpg', 4000, 4000)->size(3000);

        $response = $this->actingAs($user)
            ->postJson(route('coach.onboarding.upload-gallery-photo'), [
                'photo' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('photo');
    }

    public function test_upload_video_validates_mime_type(): void
    {
        [$user] = $this->makeCoachUser();

        $file = UploadedFile::fake()->create('bad.avi', 5000, 'video/x-msvideo');

        $response = $this->actingAs($user)
            ->postJson(route('coach.onboarding.upload-video'), ['video' => $file]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('video');
    }

    public function test_upload_photo_requires_authentication(): void
    {
        $file = UploadedFile::fake()->image('profile.jpg', 800, 800)->size(500);

        $response = $this->postJson(route('coach.onboarding.upload-photo'), [
            'photo' => $file,
            'type' => 'profile',
        ]);

        $response->assertUnauthorized();
    }

    public function test_upload_photo_rejects_invalid_type(): void
    {
        [$user] = $this->makeCoachUser();

        $file = UploadedFile::fake()->image('photo.jpg', 800, 800)->size(500);

        $response = $this->actingAs($user)
            ->postJson(route('coach.onboarding.upload-photo'), [
                'photo' => $file,
                'type' => 'invalid',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }
}
