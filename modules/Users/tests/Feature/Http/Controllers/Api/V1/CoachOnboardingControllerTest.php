<?php

namespace App\Modules\Users\Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Modules\Users\Models\Certification;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\Specialization;
use App\Modules\Users\Providers\UsersServiceProvider;
use App\Modules\Users\Services\ObjectStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Auth\Http\Middleware\JwtAuthenticate;
use Modules\Auth\Http\Middleware\RequireVerified;
use Tests\TestCase;

class CoachOnboardingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->app->register(UsersServiceProvider::class);
    }

    private function actingAsCoach(): array
    {
        $coach = Coach::factory()->create([
            'is_published' => false,
            'onboarding_completed_at' => null,
        ]);
        $user = $coach->user;
        $user->assignRole(Role::COACH);
        $user->setRelation('coach', $coach);

        return [$user, $coach];
    }

    private function asCoach($user)
    {
        return $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])->actingAs($user);
    }

    public function test_show_returns_onboarding_state(): void
    {
        [$user] = $this->actingAsCoach();
        Specialization::create(['name' => 'HIIT', 'slug' => 'hiit', 'is_custom' => false]);

        $response = $this->asCoach($user)->getJson('/api/v1/coaches/me/onboarding');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['coach', 'completed_steps', 'specializations']])
            ->assertJsonPath('data.coach.is_published', false);
    }

    public function test_step1_saves_profile_and_updates_user_name(): void
    {
        [$user, $coach] = $this->actingAsCoach();

        $response = $this->asCoach($user)->putJson('/api/v1/coaches/me/onboarding/step/1', [
            'display_name' => 'Marco Rossi',
            'tagline' => 'Il tuo coach di fiducia',
            'bio' => 'Coach esperto con 10 anni di esperienza.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.coach.display_name', 'Marco Rossi')
            ->assertJsonPath('data.completed_steps', fn ($s) => in_array(1, $s));

        $this->assertDatabaseHas('coaches', ['id' => $coach->id, 'display_name' => 'Marco Rossi']);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Marco Rossi']);
    }

    public function test_step1_validation_error_returns_422(): void
    {
        [$user] = $this->actingAsCoach();

        $response = $this->asCoach($user)->putJson('/api/v1/coaches/me/onboarding/step/1', ['display_name' => '']);

        $response->assertUnprocessable()->assertJsonValidationErrors(['display_name'], 'error.fields');
    }

    public function test_step2_syncs_specializations(): void
    {
        [$user, $coach] = $this->actingAsCoach();
        $spec = Specialization::create(['name' => 'HIIT', 'slug' => 'hiit', 'is_custom' => false]);

        $response = $this->asCoach($user)->putJson('/api/v1/coaches/me/onboarding/step/2', [
            'specialization_ids' => [$spec->id],
            'languages' => ['it', 'en'],
            'mode' => 'online',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('coach_specialization', ['coach_id' => $coach->id, 'specialization_id' => $spec->id]);
        $this->assertDatabaseHas('coaches', ['id' => $coach->id, 'mode' => 'online']);
    }

    public function test_step6_saves_pricing_and_replaces_packages(): void
    {
        [$user, $coach] = $this->actingAsCoach();

        $response = $this->asCoach($user)->putJson('/api/v1/coaches/me/onboarding/step/6', [
            'single_session_price_cents' => 5000,
            'packages' => [
                ['name' => 'Pacchetto 5', 'sessions_count' => 5, 'price_cents' => 22000],
            ],
        ]);

        $response->assertOk();
        $coach->refresh();
        $this->assertSame(5000, $coach->single_session_price_cents);
        $this->assertSame(1, $coach->packages()->count());
    }

    public function test_step8_saves_contact_preferences(): void
    {
        [$user, $coach] = $this->actingAsCoach();

        $response = $this->asCoach($user)->putJson('/api/v1/coaches/me/onboarding/step/8', [
            'free_first_session' => false,
            'welcome_discount_enabled' => false,
            'accepts_free_consultation' => true,
            'accepts_direct_message' => true,
            'accepts_direct_booking' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.coach.contact_options.free_consultation', true)
            ->assertJsonPath('data.coach.contact_options.direct_message', true)
            ->assertJsonPath('data.coach.contact_options.direct_booking', false);

        $this->assertDatabaseHas('coaches', [
            'id' => $coach->id,
            'accepts_free_consultation' => true,
            'accepts_direct_message' => true,
            'accepts_direct_booking' => false,
        ]);
    }

    private function completeCoach($coach): void
    {
        $spec = Specialization::create(['name' => 'Forza', 'slug' => 'forza', 'is_custom' => false]);
        $coach->update([
            'display_name' => 'Marco Rossi',
            'mode' => 'online',
            'session_duration_default' => '60',
            'cancellation_window_hours' => 24,
            'single_session_price_cents' => 5000,
        ]);
        $coach->specializationRecords()->sync([$spec->id]);
        $coach->availabilities()->create([
            'day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '12:00',
            'duration_minutes' => 60, 'is_recurring' => true,
        ]);
    }

    public function test_publish_sets_both_flags_when_complete(): void
    {
        [$user, $coach] = $this->actingAsCoach();
        $this->completeCoach($coach);

        $response = $this->asCoach($user)->postJson('/api/v1/coaches/me/onboarding/publish', [
            'accepted_terms' => true,
        ]);

        $response->assertOk()->assertJsonPath('data.coach.is_published', true);

        $coach->refresh();
        $this->assertTrue($coach->is_published);
        $this->assertNotNull($coach->onboarding_completed_at);
    }

    public function test_publish_returns_422_with_missing_sections_when_incomplete(): void
    {
        [$user, $coach] = $this->actingAsCoach();

        $response = $this->asCoach($user)->postJson('/api/v1/coaches/me/onboarding/publish', [
            'accepted_terms' => true,
        ]);

        $response->assertUnprocessable()
            ->assertJsonStructure(['message', 'missing_sections']);

        $coach->refresh();
        $this->assertFalse($coach->is_published);
        $this->assertNull($coach->onboarding_completed_at);
    }

    public function test_publish_requires_accepted_terms(): void
    {
        [$user, $coach] = $this->actingAsCoach();
        $this->completeCoach($coach);

        $response = $this->asCoach($user)->postJson('/api/v1/coaches/me/onboarding/publish', [
            'accepted_terms' => false,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['accepted_terms'], 'error.fields');
    }

    public function test_show_returns_gallery_media_with_ids_after_upload(): void
    {
        Storage::fake('public');
        [$user, $coach] = $this->actingAsCoach();

        $upload = $this->asCoach($user)->postJson('/api/v1/coaches/me/onboarding/gallery', [
            'photo' => UploadedFile::fake()->image('gallery.jpg', 600, 600)->size(500),
        ]);
        $upload->assertOk();

        $media = $coach->fresh()->getMedia('gallery')->first();

        $response = $this->asCoach($user)->getJson('/api/v1/coaches/me/onboarding');

        $response->assertOk()
            ->assertJsonPath('data.coach.gallery_media.0.id', $media->id)
            ->assertJsonPath('data.coach.gallery_media.0.url', $media->getUrl('medium'));
    }

    public function test_upload_profile_photo_returns_url(): void
    {
        Storage::fake('public');
        [$user, $coach] = $this->actingAsCoach();

        $response = $this->asCoach($user)->postJson('/api/v1/coaches/me/onboarding/photo', [
            'photo' => UploadedFile::fake()->image('me.jpg', 400, 400),
            'type' => 'profile',
        ]);

        $response->assertOk()->assertJsonStructure(['url']);
        $this->assertNotNull($coach->fresh()->profile_photo_url);
    }

    public function test_upload_certification_file_404_when_index_missing(): void
    {
        [$user] = $this->actingAsCoach();
        $this->mock(ObjectStorageService::class); // never called on the 404 path

        $response = $this->asCoach($user)->postJson('/api/v1/coaches/me/onboarding/certification', [
            'file' => UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf'),
            'certification_index' => 5,
        ]);

        $response->assertNotFound();
    }

    public function test_upload_certification_file_stores_and_returns_url(): void
    {
        [$user, $coach] = $this->actingAsCoach();
        Certification::create([
            'coach_id' => $coach->id, 'name' => 'PT', 'issuer' => 'CONI',
            'issued_at' => null, 'file_url' => '', 'storage_path' => '',
        ]);

        $this->mock(ObjectStorageService::class, function ($mock) {
            $mock->shouldReceive('upload')->once()->andReturn([
                'storage_path' => 'certifications/1/abc.pdf',
                'cdn_url' => 'https://cdn.test/abc.pdf',
            ]);
        });

        $response = $this->asCoach($user)->postJson('/api/v1/coaches/me/onboarding/certification', [
            'file' => UploadedFile::fake()->create('cert.pdf', 100, 'application/pdf'),
            'certification_index' => 0,
        ]);

        $response->assertOk()->assertJsonPath('url', 'https://cdn.test/abc.pdf');
    }
}
