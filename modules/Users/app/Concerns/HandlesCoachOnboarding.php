<?php

namespace App\Modules\Users\Concerns;

use App\Models\User;
use App\Modules\Users\Actions\CreateCoachProfileAction;
use App\Modules\Users\Models\Certification;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachPackage;
use App\Modules\Users\Models\Specialization;
use App\Modules\Users\Services\ObjectStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait HandlesCoachOnboarding
{
    protected function getOrCreateCoach(Request $request): Coach
    {
        $user = $request->user();
        $coach = $user->coach;

        if (! $coach) {
            $coach = app(CreateCoachProfileAction::class)->handle($user);
            $user->setRelation('coach', $coach);
        }

        return $coach;
    }

    /** @return array<int> */
    protected function resolveCompletedSteps(Coach $coach): array
    {
        $steps = [];

        if ($coach->display_name) {
            $steps[] = 1;
        }
        if ($coach->mode && $coach->specializationRecords()->exists()) {
            $steps[] = 2;
        }
        if ($coach->certificationRecords()->exists() || $coach->p_iva) {
            $steps[] = 3;
        }
        if ($coach->cancellation_window_hours && $coach->availabilities()->exists()) {
            $steps[] = 4;
        }
        if ($coach->service_radius_km || $coach->city) {
            $steps[] = 5;
        }
        if ($coach->single_session_price_cents) {
            $steps[] = 6;
        }
        if (! empty($coach->gallery_photos) || $coach->intro_video_url) {
            $steps[] = 7;
        }
        if ($coach->no_show_fee_cents || $coach->free_first_session || $coach->welcome_discount_enabled) {
            $steps[] = 8;
        }

        return $steps;
    }

    /**
     * Steps 3 (certificazioni), 5 (sede), 7 (gallery) and 8 (policy) are
     * skippable in the UI (see `OnboardingLayout.vue`) and never block advance.
     */
    private const OPTIONAL_STEPS = [3, 5, 7, 8];

    /** First non-completed, non-optional step (1–9). Optional steps don't block. */
    protected function maxReachableStep(Coach $coach): int
    {
        $completed = $this->resolveCompletedSteps($coach);

        for ($step = 1; $step <= 8; $step++) {
            if (! in_array($step, $completed, true) && ! in_array($step, self::OPTIONAL_STEPS, true)) {
                return $step;
            }
        }

        return 9;
    }

    /** @return array<string> */
    protected function resolveIncompleteSections(Coach $coach): array
    {
        $missing = [];

        if (! $coach->display_name) {
            $missing[] = 'profile';
        }
        if (! $coach->mode || ! $coach->specializationRecords()->exists()) {
            $missing[] = 'specializations';
        }
        if (! $coach->cancellation_window_hours || ! $coach->availabilities()->exists()) {
            $missing[] = 'availability';
        }
        if (! $coach->single_session_price_cents) {
            $missing[] = 'pricing';
        }

        return $missing;
    }

    /** @return array<string,mixed> */
    protected function onboardingStatePayload(Coach $coach): array
    {
        return [
            'coach' => [
                'display_name' => $coach->display_name,
                'tagline' => $coach->tagline,
                'bio' => $coach->bio,
                'gender' => $coach->gender,
                'mode' => $coach->mode,
                'city' => $coach->city,
                'lat' => $coach->lat,
                'lng' => $coach->lng,
                'languages' => $coach->languages,
                'specialization_ids' => $coach->specializationRecords()->pluck('specializations.id')->toArray(),
                'session_duration_default' => $coach->session_duration_default,
                'cancellation_window_hours' => $coach->cancellation_window_hours,
                'single_session_price_cents' => $coach->single_session_price_cents,
                'monthly_subscription_cents' => $coach->monthly_subscription_cents,
                'quarterly_subscription_cents' => $coach->quarterly_subscription_cents,
                'is_published' => $coach->is_published,
                'onboarding_completed_at' => $coach->onboarding_completed_at,
                'profile_photo_url' => $coach->profile_photo_url,
                'cover_photo_url' => $coach->cover_photo_url,
                'p_iva' => $coach->p_iva,
                'has_insurance' => $coach->has_insurance,
                'insurance_company' => $coach->insurance_company,
                'insurance_expiry' => $coach->insurance_expiry?->format('Y-m-d'),
                'insurance_file_url' => $coach->insurance_file_url,
                'service_radius_km' => $coach->service_radius_km,
                'available_at_home' => $coach->available_at_home,
                'partner_gyms' => $coach->partner_gyms,
                'gallery_photos' => $coach->gallery_photos,
                'gallery_media' => $coach->getMedia('gallery')->map(fn ($m) => [
                    'id' => $m->id,
                    'url' => $m->getUrl('medium'),
                ])->values()->all(),
                'intro_video_url' => $coach->intro_video_url,
                'no_show_fee_cents' => $coach->no_show_fee_cents,
                'late_tolerance_minutes' => $coach->late_tolerance_minutes,
                'free_first_session' => $coach->free_first_session,
                'welcome_discount_enabled' => $coach->welcome_discount_enabled,
                'welcome_discount_percent' => $coach->welcome_discount_percent,
                'custom_terms' => $coach->custom_terms,
                'contact_options' => $coach->contactOptions(),
                'certifications' => $coach->certificationRecords()->get(['id', 'name', 'issuer', 'issued_at', 'file_url']),
                'availability' => $coach->availabilities()->get(['id', 'day_of_week', 'start_time', 'end_time', 'duration_minutes']),
                'packages' => $coach->activePackages()->get(['id', 'name', 'sessions_count', 'price_cents', 'sort_order']),
            ],
            'completed_steps' => $this->resolveCompletedSteps($coach),
            'specializations' => Specialization::where('is_custom', false)
                ->orWhereIn('id', $coach->specializationRecords()->pluck('specializations.id'))
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
        ];
    }

    protected function persistStep1(Coach $coach, array $data, User $user): Coach
    {
        $user->update(['name' => $data['display_name']]);
        $coach->update([
            'display_name' => $data['display_name'],
            'tagline' => $data['tagline'] ?? null,
            'bio' => $data['bio'] ?? null,
            'gender' => $data['gender'] ?? null,
        ]);

        return $coach;
    }

    protected function persistStep2(Coach $coach, array $data): Coach
    {
        DB::transaction(function () use ($coach, $data) {
            $ids = $data['specialization_ids'] ?? [];

            foreach ($data['custom_specializations'] ?? [] as $name) {
                $slug = Str::slug($name);
                $spec = Specialization::firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $name, 'is_custom' => true, 'created_by' => $coach->user_id],
                );
                $ids[] = $spec->id;
            }

            $coach->specializationRecords()->sync(array_unique($ids));

            $coach->update([
                'languages' => $data['languages'],
                'mode' => $data['mode'],
            ]);
        });

        return $coach;
    }

    protected function persistStep3(Coach $coach, array $data): Coach
    {
        DB::transaction(function () use ($coach, $data) {
            $coach->certificationRecords()->delete();

            foreach ($data['certifications'] ?? [] as $cert) {
                Certification::create([
                    'coach_id' => $coach->id,
                    'name' => $cert['name'],
                    'issuer' => $cert['issuer'] ?? null,
                    'issued_at' => ! empty($cert['year']) ? $cert['year'].'-01-01' : null,
                    'file_url' => '',
                    'storage_path' => '',
                ]);
            }

            $coach->update([
                'p_iva' => $data['p_iva'] ?? null,
                'has_insurance' => $data['has_insurance'] ?? false,
                'insurance_company' => $data['insurance_company'] ?? null,
                'insurance_expiry' => $data['insurance_expiry'] ?? null,
            ]);
        });

        return $coach;
    }

    protected function persistStep4(Coach $coach, array $data): Coach
    {
        DB::transaction(function () use ($coach, $data) {
            $coach->availabilities()->delete();

            foreach ($data['slots'] ?? [] as $slot) {
                // skip malformed slots where end <= start
                if ($slot['end_time'] <= $slot['start_time']) {
                    continue;
                }

                $coach->availabilities()->create([
                    'day_of_week' => $slot['day_of_week'],
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'duration_minutes' => (int) $data['session_duration_default'],
                    'is_recurring' => true,
                ]);
            }

            $coach->update([
                'session_duration_default' => $data['session_duration_default'],
                'cancellation_window_hours' => (int) $data['cancellation_window_hours'],
            ]);
        });

        return $coach;
    }

    protected function persistStep5(Coach $coach, array $data): Coach
    {
        $coach->update([
            'city' => $data['city'] ?? null,
            'lat' => $data['lat'] ?? null,
            'lng' => $data['lng'] ?? null,
            'service_radius_km' => $data['service_radius_km'],
            'available_at_home' => $data['available_at_home'] ?? false,
            'partner_gyms' => $data['partner_gyms'] ?? [],
        ]);

        return $coach;
    }

    protected function persistStep6(Coach $coach, array $data): Coach
    {
        DB::transaction(function () use ($coach, $data) {
            $coach->update([
                'single_session_price_cents' => $data['single_session_price_cents'],
                'monthly_subscription_cents' => $data['monthly_subscription_cents'] ?? null,
                'quarterly_subscription_cents' => $data['quarterly_subscription_cents'] ?? null,
            ]);

            $coach->packages()->delete();

            foreach ($data['packages'] ?? [] as $index => $pkg) {
                CoachPackage::create([
                    'coach_id' => $coach->id,
                    'name' => $pkg['name'],
                    'sessions_count' => $pkg['sessions_count'],
                    'price_cents' => $pkg['price_cents'],
                    'is_active' => true,
                    'sort_order' => $index,
                ]);
            }
        });

        return $coach;
    }

    protected function persistStep7(Coach $coach, array $data): Coach
    {
        $coach->update([
            'gallery_photos' => $data['gallery_photos'] ?? [],
        ]);

        return $coach;
    }

    protected function persistStep8(Coach $coach, array $data): Coach
    {
        $coach->update([
            'no_show_fee_cents' => $data['no_show_fee_cents'] ?? null,
            'late_tolerance_minutes' => $data['late_tolerance_minutes'] ?? null,
            'free_first_session' => $data['free_first_session'] ?? false,
            'welcome_discount_enabled' => $data['welcome_discount_enabled'] ?? false,
            'welcome_discount_percent' => $data['welcome_discount_percent'] ?? null,
            'custom_terms' => $data['custom_terms'] ?? null,
        ]);

        $coach->update(array_intersect_key($data, array_flip([
            'accepts_free_consultation', 'accepts_direct_message', 'accepts_direct_booking',
        ])));

        return $coach;
    }

    /** @return array{ok: bool, missing: array<string>} */
    protected function publishCoach(Coach $coach): array
    {
        $missing = $this->resolveIncompleteSections($coach);
        if (! empty($missing)) {
            return ['ok' => false, 'missing' => $missing];
        }

        $coach->update([
            'is_published' => true,
            'onboarding_completed_at' => now(),
        ]);

        return ['ok' => true, 'missing' => []];
    }

    protected function storeCoachPhoto(Coach $coach, UploadedFile $file, string $type): string
    {
        $collection = $type === 'profile' ? 'profile_photo' : 'cover_photo';
        $media = $coach->addMedia($file)->toMediaCollection($collection);
        $url = $media->getUrl('medium');
        $field = $type === 'profile' ? 'profile_photo_url' : 'cover_photo_url';
        $coach->update([$field => $url]);

        return $url;
    }

    /** @return array{url: string, photos: array<string>} */
    protected function storeGalleryPhoto(Coach $coach, UploadedFile $file): array
    {
        $media = $coach->addMedia($file)->toMediaCollection('gallery');
        $photos = $coach->fresh()->getMedia('gallery')->map(fn ($m) => $m->getUrl('medium'))->toArray();
        $coach->update(['gallery_photos' => $photos]);

        return ['url' => $media->getUrl('medium'), 'photos' => $photos];
    }

    /** @return array{photos: array<string>}|null */
    protected function removeGalleryPhoto(Coach $coach, int $mediaId): ?array
    {
        $media = $coach->getMedia('gallery')->firstWhere('id', $mediaId);
        if (! $media) {
            return null;
        }
        $media->delete();
        $photos = $coach->fresh()->getMedia('gallery')->map(fn ($m) => $m->getUrl('medium'))->toArray();
        $coach->update(['gallery_photos' => $photos]);

        return ['photos' => $photos];
    }

    protected function storeIntroVideo(Coach $coach, UploadedFile $file): string
    {
        $media = $coach->addMedia($file)->toMediaCollection('intro_video');
        $url = $media->getUrl();
        $coach->update([
            'intro_video_url' => $url,
            'intro_video_storage_path' => $media->getPath(),
        ]);

        return $url;
    }

    protected function storeCertificationFile(Coach $coach, UploadedFile $file, int $index, ObjectStorageService $storage): ?string
    {
        $certification = $coach->certificationRecords()->skip($index)->first();
        if (! $certification) {
            return null;
        }
        $uploaded = $storage->upload($file, 'certifications/'.$coach->id, 'private');
        $url = $uploaded['cdn_url'] ?? $uploaded['storage_path'];
        $certification->update(['file_url' => $url, 'storage_path' => $uploaded['storage_path']]);

        return $url;
    }

    protected function storeInsuranceFile(Coach $coach, UploadedFile $file, ObjectStorageService $storage): string
    {
        $uploaded = $storage->upload($file, 'coaches/'.$coach->id.'/insurance', 'private');
        $url = $uploaded['cdn_url'] ?? $uploaded['storage_path'];
        $coach->update([
            'insurance_file_url' => $url,
            'insurance_file_storage_path' => $uploaded['storage_path'],
        ]);

        return $url;
    }
}
