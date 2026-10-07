<?php

namespace App\Modules\Users\Tests\Feature\Http\Controllers;

use App\Enums\Role;
use App\Enums\UserType;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\Specialization;
use App\Modules\Users\Providers\UsersServiceProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OnboardingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->app->register(UsersServiceProvider::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeCoach(bool $withOnboardingCompleted = false): array
    {
        $coach = Coach::factory()->create([
            'is_published' => false,
            'onboarding_completed_at' => $withOnboardingCompleted ? now() : null,
        ]);
        $user = $coach->user;
        $user->forceFill(['type' => UserType::COACH])->save();
        $user->assignRole(Role::COACH);
        $user->setRelation('coach', $coach);

        return [$user, $coach];
    }

    private function seedSpecializations(): Specialization
    {
        return Specialization::create([
            'name' => 'HIIT',
            'slug' => 'hiit',
            'is_custom' => false,
        ]);
    }

    // -------------------------------------------------------------------------
    // Middleware — EnsureOnboardingComplete
    // -------------------------------------------------------------------------

    public function test_coach_dashboard_redirects_to_onboarding_when_not_completed(): void
    {
        [$user] = $this->makeCoach();

        $response = $this->actingAs($user)->get(route('coach.dashboard'));

        $response->assertRedirect(route('coach.onboarding.index'));
    }

    public function test_coach_dashboard_accessible_after_onboarding_completed(): void
    {
        [$user] = $this->makeCoach(withOnboardingCompleted: true);

        $response = $this->actingAs($user)->get(route('coach.dashboard'));

        $response->assertStatus(200);
    }

    public function test_onboarding_index_requires_authentication(): void
    {
        $response = $this->get(route('coach.onboarding.index'));

        $response->assertRedirect(route('login'));
    }

    // -------------------------------------------------------------------------
    // GET /coach/onboarding
    // -------------------------------------------------------------------------

    public function test_onboarding_index_returns_inertia_response(): void
    {
        [$user] = $this->makeCoach();

        $response = $this->actingAs($user)->get(route('coach.onboarding.index'));

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Users::Coach/Onboarding/Index', false)
                ->has('specializations')
                ->has('completedSteps')
                ->has('certifications')
            );
    }

    public function test_index_does_not_let_a_new_coach_jump_ahead(): void
    {
        [$user] = $this->makeCoach();

        $this->actingAs($user)
            ->get(route('coach.onboarding.index', ['step' => 6]))
            ->assertInertia(fn ($page) => $page->where('initialStep', 1));
    }

    public function test_index_allows_going_back_to_completed_steps(): void
    {
        [$user, $coach] = $this->makeCoach();
        $coach->update(['display_name' => 'Mario']);

        $this->actingAs($user)
            ->get(route('coach.onboarding.index', ['step' => 1]))
            ->assertInertia(fn ($page) => $page->where('initialStep', 1));
        $this->actingAs($user)
            ->get(route('coach.onboarding.index', ['step' => 2]))
            ->assertInertia(fn ($page) => $page->where('initialStep', 2));
        $this->actingAs($user)
            ->get(route('coach.onboarding.index', ['step' => 4]))
            ->assertInertia(fn ($page) => $page->where('initialStep', 2));
    }

    // -------------------------------------------------------------------------
    // PUT /coach/onboarding/step/1 — Profile
    // -------------------------------------------------------------------------

    public function test_step1_saves_display_name_and_tagline(): void
    {
        [$user, $coach] = $this->makeCoach();

        $response = $this->actingAs($user)->put(route('coach.onboarding.step1'), [
            'display_name' => 'Marco Rossi',
            'tagline' => 'Il tuo coach di fiducia',
            'bio' => 'Coach esperto con 10 anni di esperienza.',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('coaches', [
            'id' => $coach->id,
            'display_name' => 'Marco Rossi',
            'tagline' => 'Il tuo coach di fiducia',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Marco Rossi',
        ]);
    }

    public function test_step1_requires_display_name(): void
    {
        [$user] = $this->makeCoach();

        $response = $this->actingAs($user)->put(route('coach.onboarding.step1'), [
            'display_name' => '',
        ]);

        $response->assertSessionHasErrors('display_name');
    }

    public function test_step1_tagline_max_120_chars(): void
    {
        [$user] = $this->makeCoach();

        $response = $this->actingAs($user)->put(route('coach.onboarding.step1'), [
            'display_name' => 'Marco',
            'tagline' => str_repeat('x', 121),
        ]);

        $response->assertSessionHasErrors('tagline');
    }

    public function test_step1_rejects_short_bio_with_a_bio_error(): void
    {
        [$user] = $this->makeCoach();

        $response = $this->actingAs($user)->put(route('coach.onboarding.step1'), [
            'display_name' => 'Mario Rossi',
            'tagline' => 'Personal trainer',
            'bio' => 'corta',
        ]);

        $response->assertSessionHasErrors(['bio']);
    }

    public function test_step1_saves_gender(): void
    {
        [$user, $coach] = $this->makeCoach();

        $response = $this->actingAs($user)->put(route('coach.onboarding.step1'), [
            'display_name' => 'Giulia Bianchi',
            'tagline' => 'Pilates e postura',
            'bio' => 'Insegno pilates da dieci anni.',
            'gender' => 'female',
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertSame('female', $coach->fresh()->gender);
    }

    public function test_step1_gender_is_optional(): void
    {
        [$user, $coach] = $this->makeCoach();

        $response = $this->actingAs($user)->put(route('coach.onboarding.step1'), [
            'display_name' => 'Giulia Bianchi',
            'tagline' => 'Pilates e postura',
            'bio' => 'Insegno pilates da dieci anni.',
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertNull($coach->fresh()->gender);
    }

    public function test_step1_rejects_invalid_gender(): void
    {
        [$user] = $this->makeCoach();

        $response = $this->actingAs($user)->put(route('coach.onboarding.step1'), [
            'display_name' => 'Giulia Bianchi',
            'tagline' => 'Pilates e postura',
            'bio' => 'Insegno pilates da dieci anni.',
            'gender' => 'other',
        ]);

        $response->assertSessionHasErrors('gender');
    }

    // -------------------------------------------------------------------------
    // PUT /coach/onboarding/step/2 — Specializations
    // -------------------------------------------------------------------------

    public function test_step2_syncs_specializations_and_updates_mode(): void
    {
        [$user, $coach] = $this->makeCoach();
        $spec = $this->seedSpecializations();

        $response = $this->actingAs($user)->put(route('coach.onboarding.step2'), [
            'specialization_ids' => [$spec->id],
            'languages' => ['it', 'en'],
            'mode' => 'online',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('coach_specialization', [
            'coach_id' => $coach->id,
            'specialization_id' => $spec->id,
        ]);

        $this->assertDatabaseHas('coaches', [
            'id' => $coach->id,
            'mode' => 'online',
        ]);
    }

    public function test_step2_creates_custom_specialization(): void
    {
        [$user, $coach] = $this->makeCoach();
        $spec = $this->seedSpecializations();

        $this->actingAs($user)->put(route('coach.onboarding.step2'), [
            'specialization_ids' => [$spec->id],
            'custom_specializations' => ['Kettlebell'],
            'languages' => ['it'],
            'mode' => 'hybrid',
        ]);

        $this->assertDatabaseHas('specializations', [
            'slug' => 'kettlebell',
            'is_custom' => true,
        ]);

        $this->assertSame(2, $coach->fresh()->specializationRecords()->count());
    }

    public function test_step2_requires_at_least_one_specialization(): void
    {
        [$user] = $this->makeCoach();

        $response = $this->actingAs($user)->put(route('coach.onboarding.step2'), [
            'specialization_ids' => [],
            'languages' => ['it'],
            'mode' => 'online',
        ]);

        $response->assertSessionHasErrors('specialization_ids');
    }

    public function test_step2_accepts_only_custom_specializations(): void
    {
        [$user, $coach] = $this->makeCoach();

        $response = $this->actingAs($user)->put(route('coach.onboarding.step2'), [
            'specialization_ids' => [],
            'custom_specializations' => ['Functional Bulgaro'],
            'languages' => ['it'],
            'mode' => 'online',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('specializations', [
            'slug' => 'functional-bulgaro',
            'is_custom' => true,
        ]);

        $this->assertSame(1, $coach->fresh()->specializationRecords()->count());
    }

    // -------------------------------------------------------------------------
    // PUT /coach/onboarding/step/3 — Certifications
    // -------------------------------------------------------------------------

    public function test_step3_saves_certifications_and_piva(): void
    {
        [$user, $coach] = $this->makeCoach();

        $response = $this->actingAs($user)->put(route('coach.onboarding.step3'), [
            'certifications' => [
                ['name' => 'Personal Trainer', 'issuer' => 'CONI', 'year' => '2022'],
            ],
            'p_iva' => '00470550013',
            'has_insurance' => true,
            'insurance_company' => 'Zurich',
            'insurance_expiry' => '2027-12-31',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('certifications', [
            'coach_id' => $coach->id,
            'name' => 'Personal Trainer',
            'issuer' => 'CONI',
        ]);

        $this->assertDatabaseHas('coaches', [
            'id' => $coach->id,
            'p_iva' => '00470550013',
            'has_insurance' => true,
            'insurance_company' => 'Zurich',
        ]);
    }

    // -------------------------------------------------------------------------
    // PUT /coach/onboarding/step/4 — Availability
    // -------------------------------------------------------------------------

    public function test_step4_saves_availability_slots(): void
    {
        [$user, $coach] = $this->makeCoach();

        $response = $this->actingAs($user)->put(route('coach.onboarding.step4'), [
            'slots' => [
                ['day_of_week' => 0, 'start_time' => '08:00', 'end_time' => '12:00'],
                ['day_of_week' => 2, 'start_time' => '14:00', 'end_time' => '18:00'],
            ],
            'session_duration_default' => '60',
            'cancellation_window_hours' => '24',
        ]);

        $response->assertRedirect();

        $this->assertSame(2, $coach->fresh()->availabilities()->count());
        $this->assertDatabaseHas('coaches', [
            'id' => $coach->id,
            'session_duration_default' => '60',
            'cancellation_window_hours' => 24,
        ]);
    }

    public function test_step4_replaces_existing_slots(): void
    {
        [$user, $coach] = $this->makeCoach();

        $this->actingAs($user)->put(route('coach.onboarding.step4'), [
            'slots' => [
                ['day_of_week' => 0, 'start_time' => '08:00', 'end_time' => '12:00'],
                ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '13:00'],
            ],
            'session_duration_default' => '60',
            'cancellation_window_hours' => '12',
        ]);

        $this->actingAs($user)->put(route('coach.onboarding.step4'), [
            'slots' => [
                ['day_of_week' => 4, 'start_time' => '10:00', 'end_time' => '14:00'],
            ],
            'session_duration_default' => '90',
            'cancellation_window_hours' => '48',
        ]);

        $this->assertSame(1, $coach->fresh()->availabilities()->count());
    }

    // -------------------------------------------------------------------------
    // PUT /coach/onboarding/step/5 — Sede
    // -------------------------------------------------------------------------

    public function test_step5_saves_sede_data(): void
    {
        [$user, $coach] = $this->makeCoach();

        $response = $this->actingAs($user)->put(route('coach.onboarding.step5'), [
            'city' => 'Milano',
            'lat' => 45.4642,
            'lng' => 9.19,
            'service_radius_km' => 25,
            'available_at_home' => true,
            'partner_gyms' => ['McFit Duomo', 'Virgin Active'],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('coaches', [
            'id' => $coach->id,
            'city' => 'Milano',
            'service_radius_km' => 25,
            'available_at_home' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // PUT /coach/onboarding/step/6 — Pricing
    // -------------------------------------------------------------------------

    public function test_step6_saves_pricing_and_packages(): void
    {
        [$user, $coach] = $this->makeCoach();

        $response = $this->actingAs($user)->put(route('coach.onboarding.step6'), [
            'single_session_price_cents' => 5000,
            'monthly_subscription_cents' => 40000,
            'packages' => [
                ['name' => 'Pacchetto 5 sessioni', 'sessions_count' => 5, 'price_cents' => 22000],
                ['name' => 'Pacchetto 10 sessioni', 'sessions_count' => 10, 'price_cents' => 40000],
            ],
        ]);

        $response->assertRedirect();

        $coach->refresh();
        $this->assertSame(5000, $coach->single_session_price_cents);
        $this->assertSame(40000, $coach->monthly_subscription_cents);
        $this->assertSame(2, $coach->packages()->count());

        $this->assertDatabaseHas('coach_packages', [
            'coach_id' => $coach->id,
            'name' => 'Pacchetto 5 sessioni',
            'sessions_count' => 5,
            'price_cents' => 22000,
        ]);
    }

    public function test_step6_requires_single_session_price(): void
    {
        [$user] = $this->makeCoach();

        $response = $this->actingAs($user)->put(route('coach.onboarding.step6'), [
            'single_session_price_cents' => 0,
        ]);

        $response->assertSessionHasErrors('single_session_price_cents');
    }

    // -------------------------------------------------------------------------
    // PUT /coach/onboarding/step/8 — Policy
    // -------------------------------------------------------------------------

    public function test_step8_saves_policy_data(): void
    {
        [$user, $coach] = $this->makeCoach();

        $response = $this->actingAs($user)->put(route('coach.onboarding.step8'), [
            'no_show_fee_cents' => 5000,
            'late_tolerance_minutes' => 15,
            'free_first_session' => true,
            'welcome_discount_enabled' => true,
            'welcome_discount_percent' => 20,
            'custom_terms' => 'Materiale fornito dal coach.',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('coaches', [
            'id' => $coach->id,
            'no_show_fee_cents' => 5000,
            'late_tolerance_minutes' => 15,
            'free_first_session' => true,
            'welcome_discount_enabled' => true,
            'welcome_discount_percent' => 20,
        ]);
    }

    public function test_step8_saves_contact_preferences(): void
    {
        [$user, $coach] = $this->makeCoach();

        $this->actingAs($user)->put(route('coach.onboarding.step8'), [
            'free_first_session' => false,
            'welcome_discount_enabled' => false,
            'accepts_free_consultation' => true,
            'accepts_direct_message' => true,
            'accepts_direct_booking' => false,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('coaches', [
            'id' => $coach->id,
            'accepts_free_consultation' => true,
            'accepts_direct_message' => true,
            'accepts_direct_booking' => false,
        ]);
    }

    public function test_step8_requires_at_least_one_contact_channel(): void
    {
        [$user] = $this->makeCoach();

        $this->actingAs($user)->put(route('coach.onboarding.step8'), [
            'free_first_session' => false,
            'welcome_discount_enabled' => false,
            'accepts_free_consultation' => false,
            'accepts_direct_message' => false,
            'accepts_direct_booking' => false,
        ])->assertSessionHasErrors(['contact_channels']);
    }

    public function test_step8_without_contact_fields_keeps_defaults_for_older_mobile_clients(): void
    {
        [$user, $coach] = $this->makeCoach();

        $this->actingAs($user)->put(route('coach.onboarding.step8'), [
            'free_first_session' => false,
            'welcome_discount_enabled' => false,
        ])->assertSessionHasNoErrors();

        $this->assertTrue($coach->fresh()->accepts_direct_booking);
    }

    public function test_onboarding_index_exposes_contact_options(): void
    {
        [$user] = $this->makeCoach();

        $this->actingAs($user)->get(route('coach.onboarding.index'))
            ->assertInertia(fn ($page) => $page
                ->where('coach.contact_options.direct_booking', true)
                ->where('coach.contact_options.direct_message', false)
                ->where('coach.contact_options.free_consultation', false));
    }

    // -------------------------------------------------------------------------
    // POST /coach/onboarding/publish
    // -------------------------------------------------------------------------

    public function test_publish_completes_onboarding_and_redirects_to_dashboard(): void
    {
        [$user, $coach] = $this->makeCoach();
        $spec = $this->seedSpecializations();

        $coach->update([
            'display_name' => 'Marco Rossi',
            'mode' => 'online',
            'session_duration_default' => '60',
            'cancellation_window_hours' => 24,
            'single_session_price_cents' => 5000,
        ]);
        $coach->specializationRecords()->sync([$spec->id]);
        $coach->availabilities()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'duration_minutes' => 60,
            'is_recurring' => true,
        ]);

        $response = $this->actingAs($user)->post(route('coach.onboarding.publish'), [
            'accepted_terms' => '1',
        ]);

        $response->assertRedirect(route('coach.dashboard'));

        $coach->refresh();
        $this->assertTrue($coach->is_published);
        $this->assertNotNull($coach->onboarding_completed_at);
    }

    public function test_publish_fails_when_profile_incomplete(): void
    {
        [$user, $coach] = $this->makeCoach();

        $response = $this->actingAs($user)->post(route('coach.onboarding.publish'), [
            'accepted_terms' => '1',
        ]);

        $response->assertSessionHasErrors('completeness');

        $coach->refresh();
        $this->assertFalse($coach->is_published);
        $this->assertNull($coach->onboarding_completed_at);
    }

    public function test_publish_requires_accepted_terms(): void
    {
        [$user] = $this->makeCoach();

        $response = $this->actingAs($user)->post(route('coach.onboarding.publish'), [
            'accepted_terms' => '0',
        ]);

        $response->assertSessionHasErrors('accepted_terms');
    }

    // -------------------------------------------------------------------------
    // POST /coach/onboarding/upload-gallery-photo - Gallery (step 7)
    // -------------------------------------------------------------------------

    public function test_uploading_a_gallery_photo_stores_it_and_completes_step7(): void
    {
        Storage::fake('public');
        [$user, $coach] = $this->makeCoach();

        $this->assertNotContains(7, $this->completedStepsFor($user));

        $response = $this->actingAs($user)->post(route('coach.onboarding.upload-gallery-photo'), [
            'photo' => UploadedFile::fake()->image('gallery.jpg'),
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['url', 'photos']);

        $coach->refresh();
        $this->assertCount(1, $coach->gallery_photos);
        $this->assertSame(1, $coach->getMedia('gallery')->count());
        $this->assertContains(7, $this->completedStepsFor($user));
    }

    // -------------------------------------------------------------------------
    // Logout during onboarding
    // -------------------------------------------------------------------------

    public function test_coach_can_log_out_before_completing_onboarding(): void
    {
        [$user] = $this->makeCoach();

        $this->actingAs($user)->post(route('logout'))->assertRedirect();

        $this->assertGuest();
    }

    /** @return array<int> */
    private function completedStepsFor(Authenticatable $user): array
    {
        $response = $this->actingAs($user)->get(route('coach.onboarding.index'));
        $response->assertOk();

        return $response->viewData('page')['props']['completedSteps'];
    }
}
