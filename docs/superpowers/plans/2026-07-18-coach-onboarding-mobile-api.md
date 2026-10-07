# Coach Onboarding Mobile JSON API — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expose the existing web coach-onboarding 9-step wizard as a JSON API under `/api/v1/coaches/me/onboarding/*` so the Flutter app can drive full-parity onboarding.

**Architecture:** Extract the web `OnboardingController`'s persistence + read logic into a shared `HandlesCoachOnboarding` trait (behavior-preserving refactor, guarded by the existing web test suite). Add a new `Api\V1\CoachOnboardingController` that reuses the trait and the existing `OnboardingStepXRequest` FormRequests, returning JSON `{data: ...}` instead of Inertia/redirects. Entirely additive: the flat `PATCH coaches/me` API and the web wizard behavior are untouched.

**Tech Stack:** Laravel (modular, `modules/Users`), PHPUnit feature tests with `RefreshDatabase`, Spatie MediaLibrary, `ObjectStorageService`, JWT auth (`jwt.auth` + `verified.email` + `role:coach`).

## Global Constraints

- Module namespace: `App\Modules\Users\...` (files under `modules/Users/app/...`).
- All new routes live inside the existing `Route::prefix('coaches/me')->middleware('role:coach')` group in `modules/Users/routes/api.php` (inherits `jwt.auth` + `verified.email`). Route-name prefix `coaches.me.onboarding.`.
- Success envelope: `{ "data": ... }`. Publish completeness failure: HTTP 422 `{ "message": ..., "missing_sections": [...] }`.
- Reuse existing FormRequests verbatim: `OnboardingStep1Request`..`OnboardingStep8Request`, `OnboardingPublishRequest` (namespace `App\Modules\Users\Http\Requests`). Do NOT modify them.
- Refactors of the web `OnboardingController` MUST be behavior-preserving: the existing `modules/Users/tests/Feature/Http/Controllers/OnboardingControllerTest.php` must stay green after every task.
- Publish sets BOTH `is_published = true` AND `onboarding_completed_at = now()`.
- Test auth pattern (copy from `CoachPackageControllerTest`): `->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])->actingAs($user)`; coach built via `Coach::factory()->create()` + `$user->assignRole(Role::COACH)` + `$user->setRelation('coach', $coach)`; test class sets `beforeRefreshingDatabase()` → `$this->app->register(UsersServiceProvider::class)`.
- Run a single test: `php artisan test --filter <TestClass>` (or Sail: `./vendor/bin/sail artisan test --filter <TestClass>`). Use whichever the repo uses; commands below use `php artisan test`.

---

## File Structure

- Create: `modules/Users/app/Concerns/HandlesCoachOnboarding.php` — trait: `getOrCreateCoach`, `resolveCompletedSteps`, `resolveIncompleteSections`, `onboardingStatePayload`, `persistStep1`..`persistStep8`, `publishCoach`, upload helpers (`storeCoachPhoto`, `storeGalleryPhoto`, `deleteGalleryPhoto`, `storeIntroVideo`, `storeCertificationFile`, `storeInsuranceFile`).
- Modify: `modules/Users/app/Http/Controllers/OnboardingController.php` — delegate to the trait (web behavior unchanged).
- Create: `modules/Users/app/Http/Controllers/Api/V1/CoachOnboardingController.php` — JSON controller.
- Modify: `modules/Users/routes/api.php` — add onboarding route group.
- Create: `modules/Users/tests/Feature/Http/Controllers/Api/V1/CoachOnboardingControllerTest.php` — API feature tests.

---

## Task 1: Extract read/create helpers into `HandlesCoachOnboarding` trait

Behavior-preserving. Move the three private read helpers out of the web controller into a trait, plus a unified `onboardingStatePayload` used by both the web `index` and (later) the API `show`.

**Files:**
- Create: `modules/Users/app/Concerns/HandlesCoachOnboarding.php`
- Modify: `modules/Users/app/Http/Controllers/OnboardingController.php`
- Test (existing, as gate): `modules/Users/tests/Feature/Http/Controllers/OnboardingControllerTest.php`

**Interfaces:**
- Produces:
  - `getOrCreateCoach(Request $request): Coach`
  - `resolveCompletedSteps(Coach $coach): array` (int[])
  - `resolveIncompleteSections(Coach $coach): array` (string[])
  - `onboardingStatePayload(Coach $coach): array` — keys: `coach` (array), `completed_steps` (int[]), `specializations` (catalog array).

- [ ] **Step 1: Run the existing web onboarding test to confirm current green baseline**

Run: `php artisan test --filter OnboardingControllerTest`
Expected: PASS (all existing tests green — this is the refactor safety net).

- [ ] **Step 2: Create the trait with the three helpers moved verbatim + the payload builder**

Create `modules/Users/app/Concerns/HandlesCoachOnboarding.php`:

```php
<?php

namespace App\Modules\Users\Concerns;

use App\Modules\Users\Actions\CreateCoachProfileAction;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\Specialization;
use Illuminate\Http\Request;

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
                'intro_video_url' => $coach->intro_video_url,
                'no_show_fee_cents' => $coach->no_show_fee_cents,
                'late_tolerance_minutes' => $coach->late_tolerance_minutes,
                'free_first_session' => $coach->free_first_session,
                'welcome_discount_enabled' => $coach->welcome_discount_enabled,
                'welcome_discount_percent' => $coach->welcome_discount_percent,
                'custom_terms' => $coach->custom_terms,
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
}
```

- [ ] **Step 3: Refactor the web controller to `use` the trait and delete the moved private methods**

In `modules/Users/app/Http/Controllers/OnboardingController.php`:
- Add `use App\Modules\Users\Concerns\HandlesCoachOnboarding;` (top) and `use HandlesCoachOnboarding;` inside the class body.
- Delete the now-duplicated private methods `getOrCreateCoach`, `resolveCompletedSteps`, `resolveIncompleteSections` from the controller (they now live in the trait).
- In `index()`, keep the Inertia render but source its data from the payload so web and API agree. Replace the manual `'coach' => [...]`, `'completedSteps' => ...`, `'specializations' => ...` array assembly with the trait payload, mapping to the web's existing Inertia prop names:

```php
public function index(Request $request): Response
{
    $coach = $this->getOrCreateCoach($request);
    $initialStep = min(9, max(1, (int) $request->query('step', 1)));
    $state = $this->onboardingStatePayload($coach);

    return Inertia::render('Users::Coach/Onboarding/Index', [
        'initialStep' => $initialStep,
        'coach' => $state['coach'],
        'completedSteps' => $state['completed_steps'],
        'specializations' => $state['specializations'],
        'packages' => $coach->activePackages()->get(['id', 'name', 'sessions_count', 'price_cents', 'sort_order']),
        'availabilitySlots' => $coach->availabilities()->get(['id', 'day_of_week', 'start_time', 'end_time', 'duration_minutes']),
        'certifications' => $coach->certificationRecords()->get(['id', 'name', 'issuer', 'issued_at', 'file_url']),
    ])->withoutSSR();
}
```

(Note: the web Inertia view reads `coach.display_name`, `coach.tagline`, etc. — `$state['coach']` preserves every key the previous inline array had, so the Vue props are unchanged. `packages`/`availabilitySlots`/`certifications` top-level props are kept as-is for the existing view.)

- [ ] **Step 4: Run the existing web onboarding test to confirm the refactor is behavior-preserving**

Run: `php artisan test --filter OnboardingControllerTest`
Expected: PASS (unchanged — `test_onboarding_index_returns_inertia_response` still asserts `specializations`/`completedSteps`/`certifications` props exist).

- [ ] **Step 5: Commit**

```bash
git add modules/Users/app/Concerns/HandlesCoachOnboarding.php modules/Users/app/Http/Controllers/OnboardingController.php
git commit -m "refactor(onboarding): extract read/create helpers into HandlesCoachOnboarding trait"
```

---

## Task 2: Move step 1–8 persistence into the trait

Behavior-preserving. Extract each step's persistence body into a trait method returning the updated `Coach`; the web controller's `updateStepN` becomes "persist then redirect".

**Files:**
- Modify: `modules/Users/app/Concerns/HandlesCoachOnboarding.php`
- Modify: `modules/Users/app/Http/Controllers/OnboardingController.php`
- Test (gate): `modules/Users/tests/Feature/Http/Controllers/OnboardingControllerTest.php`

**Interfaces:**
- Consumes: `getOrCreateCoach` (Task 1).
- Produces: `persistStep1(Coach $coach, array $data, User $user): Coach`, `persistStep2`..`persistStep8(Coach $coach, array $data): Coach`.

- [ ] **Step 1: Add the eight persist methods to the trait (bodies moved verbatim from the web controller)**

Append to `HandlesCoachOnboarding` (add `use App\Modules\Users\Models\{Certification, CoachPackage};`, `use App\Models\User;` — confirm the User model FQCN used elsewhere in the module; use `Illuminate\Support\Facades\DB;` and `Illuminate\Support\Str;`):

```php
protected function persistStep1(Coach $coach, array $data, User $user): Coach
{
    $user->update(['name' => $data['display_name']]);
    $coach->update([
        'display_name' => $data['display_name'],
        'tagline' => $data['tagline'] ?? null,
        'bio' => $data['bio'] ?? null,
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
        $coach->update(['languages' => $data['languages'], 'mode' => $data['mode']]);
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
    $coach->update(['gallery_photos' => $data['gallery_photos'] ?? []]);

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

    return $coach;
}
```

- [ ] **Step 2: Refactor the web controller's `updateStep1`..`updateStep8` to delegate**

Each web method becomes persist-then-redirect. Example for step 1 and 2 (apply the same shape to 3–8, calling the matching `persistStepN`; steps 2–8 take only `($coach, $data)`):

```php
public function updateStep1(OnboardingStep1Request $request): RedirectResponse
{
    $coach = $this->getOrCreateCoach($request);
    $this->persistStep1($coach, $request->validated(), $request->user());

    return redirect()->route('coach.onboarding.index', ['step' => 2])
        ->with('success', 'Profilo aggiornato.');
}

public function updateStep2(OnboardingStep2Request $request): RedirectResponse
{
    $coach = $this->getOrCreateCoach($request);
    $this->persistStep2($coach, $request->validated());

    return redirect()->route('coach.onboarding.index', ['step' => 3])
        ->with('success', 'Specializzazioni aggiornate.');
}
```

Preserve each step's existing redirect target (`step => N+1`) and its `success` flash message string exactly as they are today.

- [ ] **Step 3: Run the web onboarding test — persistence unchanged**

Run: `php artisan test --filter OnboardingControllerTest`
Expected: PASS (step1–8 persistence assertions unchanged).

- [ ] **Step 4: Commit**

```bash
git add modules/Users/app/Concerns/HandlesCoachOnboarding.php modules/Users/app/Http/Controllers/OnboardingController.php
git commit -m "refactor(onboarding): move step 1-8 persistence into trait"
```

---

## Task 3: Move publish + upload logic into the trait

Behavior-preserving. Extract the publish decision and the six upload/delete operations so the API controller reuses them.

**Files:**
- Modify: `modules/Users/app/Concerns/HandlesCoachOnboarding.php`
- Modify: `modules/Users/app/Http/Controllers/OnboardingController.php`
- Test (gate): `modules/Users/tests/Feature/Http/Controllers/OnboardingControllerTest.php`

**Interfaces:**
- Produces:
  - `publishCoach(Coach $coach): array` → `['ok' => bool, 'missing' => string[]]`; on ok sets `is_published=true` + `onboarding_completed_at=now()`.
  - `storeCoachPhoto(Coach $coach, UploadedFile $file, string $type): string` (returns url)
  - `storeGalleryPhoto(Coach $coach, UploadedFile $file): array` → `['url' => string, 'photos' => string[]]`
  - `removeGalleryPhoto(Coach $coach, int $mediaId): ?array` → `['photos' => string[]]` or null if not found
  - `storeIntroVideo(Coach $coach, UploadedFile $file): string`
  - `storeCertificationFile(Coach $coach, UploadedFile $file, int $index, ObjectStorageService $storage): ?string` (null if index missing)
  - `storeInsuranceFile(Coach $coach, UploadedFile $file, ObjectStorageService $storage): string`

- [ ] **Step 1: Add `publishCoach` + upload helpers to the trait (bodies from the web controller)**

Append to the trait (add imports `Illuminate\Http\UploadedFile;`, `App\Modules\Users\Services\ObjectStorageService;`):

```php
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
```

Note: the web controller used `addMediaFromRequest('photo')`; the trait takes an explicit `UploadedFile` (`addMedia($file)`) so the same helper serves both `$request->file(...)` callers. Web controller must pass `$request->file('photo'|'video')`.

- [ ] **Step 2: Refactor the web controller's `publish` + upload methods to delegate**

`publish` keeps its `OnboardingPublishRequest`, gate, flash, and redirect — but the flag write goes through `publishCoach`:

```php
public function publish(OnboardingPublishRequest $request): RedirectResponse
{
    $coach = $this->getOrCreateCoach($request);
    $result = $this->publishCoach($coach);

    if (! $result['ok']) {
        return back()->withErrors(['completeness' => 'Completa tutte le sezioni prima di pubblicare.'])
            ->with('missing_sections', $result['missing']);
    }

    return redirect()->route('coach.dashboard')
        ->with('success', 'Profilo pubblicato! Benvenuto su MioCoach.');
}
```

Refactor `uploadPhoto`, `uploadGalleryPhoto`, `deleteGalleryPhoto`, `uploadVideo`, `uploadCertification`, `uploadInsurance` to keep their inline `$request->validate([...])` blocks (unchanged), then call the trait helper with `$request->file(...)`, preserving their existing JSON return shapes and the gallery 6-photo cap / 404 responses. Example:

```php
public function uploadPhoto(Request $request): JsonResponse
{
    $request->validate([
        'photo' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
        'type' => ['required', 'in:profile,cover'],
    ]);
    $coach = $this->getOrCreateCoach($request);
    $url = $this->storeCoachPhoto($coach, $request->file('photo'), $request->input('type'));

    return response()->json(['url' => $url]);
}

public function uploadGalleryPhoto(Request $request): JsonResponse
{
    $request->validate(['photo' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp']]);
    $coach = $this->getOrCreateCoach($request);
    if ($coach->getMedia('gallery')->count() >= 6) {
        return response()->json(['error' => 'Limite massimo di 6 foto raggiunto.'], 422);
    }
    $result = $this->storeGalleryPhoto($coach, $request->file('photo'));

    return response()->json($result);
}
```

Apply the analogous delegate-and-return for `deleteGalleryPhoto` (use `removeGalleryPhoto`, 404 when null), `uploadVideo` (`storeIntroVideo`), `uploadCertification` (`storeCertificationFile`, 404 when null), `uploadInsurance` (`storeInsuranceFile`). Keep every existing validation array and response body verbatim.

- [ ] **Step 3: Run the web onboarding test suite**

Run: `php artisan test --filter OnboardingControllerTest`
Expected: PASS (publish + existing behavior unchanged).

- [ ] **Step 4: Commit**

```bash
git add modules/Users/app/Concerns/HandlesCoachOnboarding.php modules/Users/app/Http/Controllers/OnboardingController.php
git commit -m "refactor(onboarding): move publish + upload logic into trait"
```

---

## Task 4: Add API routes + `CoachOnboardingController::show`

First API surface: the GET state endpoint + the controller shell.

**Files:**
- Create: `modules/Users/app/Http/Controllers/Api/V1/CoachOnboardingController.php`
- Modify: `modules/Users/routes/api.php`
- Create: `modules/Users/tests/Feature/Http/Controllers/Api/V1/CoachOnboardingControllerTest.php`

**Interfaces:**
- Consumes: trait `getOrCreateCoach`, `onboardingStatePayload`.
- Produces: `GET /api/v1/coaches/me/onboarding` → `200 {data: {coach, completed_steps, specializations}}`.

- [ ] **Step 1: Write the failing test**

Create `modules/Users/tests/Feature/Http/Controllers/Api/V1/CoachOnboardingControllerTest.php`:

```php
<?php

namespace App\Modules\Users\Tests\Feature\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\Specialization;
use App\Modules\Users\Providers\UsersServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter CoachOnboardingControllerTest`
Expected: FAIL (404 route not found).

- [ ] **Step 3: Create the controller with `show`**

Create `modules/Users/app/Http/Controllers/Api/V1/CoachOnboardingController.php`:

```php
<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Concerns\HandlesCoachOnboarding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoachOnboardingController extends Controller
{
    use HandlesCoachOnboarding;

    public function show(Request $request): JsonResponse
    {
        $coach = $this->getOrCreateCoach($request);

        return response()->json(['data' => $this->onboardingStatePayload($coach)]);
    }
}
```

- [ ] **Step 4: Register the route group**

In `modules/Users/routes/api.php`, inside the `Route::prefix('coaches/me')->name('coaches.me.')->middleware('role:coach')` group, add the import at top:
`use App\Modules\Users\Http\Controllers\Api\V1\CoachOnboardingController;`
and the route block:

```php
Route::prefix('onboarding')->name('onboarding.')->group(function () {
    Route::get('', [CoachOnboardingController::class, 'show'])->middleware('throttle:60,1')->name('show');
});
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter CoachOnboardingControllerTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add modules/Users/app/Http/Controllers/Api/V1/CoachOnboardingController.php modules/Users/routes/api.php modules/Users/tests/Feature/Http/Controllers/Api/V1/CoachOnboardingControllerTest.php
git commit -m "feat(onboarding-api): add GET coaches/me/onboarding state endpoint"
```

---

## Task 5: API step endpoints 1–8

Add the eight PUT step endpoints, each reusing its `OnboardingStepXRequest` + the trait persist method, returning `{data: {coach, completed_steps}}`.

**Files:**
- Modify: `modules/Users/app/Http/Controllers/Api/V1/CoachOnboardingController.php`
- Modify: `modules/Users/routes/api.php`
- Modify: `modules/Users/tests/Feature/Http/Controllers/Api/V1/CoachOnboardingControllerTest.php`

**Interfaces:**
- Consumes: trait `persistStep1`..`persistStep8`, `onboardingStatePayload`.
- Produces: `PUT /api/v1/coaches/me/onboarding/step/{1..8}` → `200 {data: {coach, completed_steps}}`; validation failure → `422`.

- [ ] **Step 1: Write failing tests for representative steps (1, 2, 6) + a validation case**

Add to `CoachOnboardingControllerTest`:

```php
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

    $response->assertUnprocessable()->assertJsonValidationErrors(['display_name']);
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter CoachOnboardingControllerTest`
Expected: FAIL (404 — step routes not defined).

- [ ] **Step 3: Add the eight step methods to the controller**

Add imports for the requests (`use App\Modules\Users\Http\Requests\OnboardingStep1Request;` … `OnboardingStep8Request;`) and the methods. Each returns the state payload restricted to `coach` + `completed_steps`:

```php
private function stepResponse($coach): JsonResponse
{
    $state = $this->onboardingStatePayload($coach->fresh());

    return response()->json(['data' => [
        'coach' => $state['coach'],
        'completed_steps' => $state['completed_steps'],
    ]]);
}

public function updateStep1(OnboardingStep1Request $request): JsonResponse
{
    $coach = $this->getOrCreateCoach($request);
    $this->persistStep1($coach, $request->validated(), $request->user());

    return $this->stepResponse($coach);
}

public function updateStep2(OnboardingStep2Request $request): JsonResponse
{
    $coach = $this->getOrCreateCoach($request);
    $this->persistStep2($coach, $request->validated());

    return $this->stepResponse($coach);
}

public function updateStep3(OnboardingStep3Request $request): JsonResponse
{
    $coach = $this->getOrCreateCoach($request);
    $this->persistStep3($coach, $request->validated());

    return $this->stepResponse($coach);
}

public function updateStep4(OnboardingStep4Request $request): JsonResponse
{
    $coach = $this->getOrCreateCoach($request);
    $this->persistStep4($coach, $request->validated());

    return $this->stepResponse($coach);
}

public function updateStep5(OnboardingStep5Request $request): JsonResponse
{
    $coach = $this->getOrCreateCoach($request);
    $this->persistStep5($coach, $request->validated());

    return $this->stepResponse($coach);
}

public function updateStep6(OnboardingStep6Request $request): JsonResponse
{
    $coach = $this->getOrCreateCoach($request);
    $this->persistStep6($coach, $request->validated());

    return $this->stepResponse($coach);
}

public function updateStep7(OnboardingStep7Request $request): JsonResponse
{
    $coach = $this->getOrCreateCoach($request);
    $this->persistStep7($coach, $request->validated());

    return $this->stepResponse($coach);
}

public function updateStep8(OnboardingStep8Request $request): JsonResponse
{
    $coach = $this->getOrCreateCoach($request);
    $this->persistStep8($coach, $request->validated());

    return $this->stepResponse($coach);
}
```

- [ ] **Step 4: Add the step routes**

In the `onboarding` route group (Task 4), add:

```php
Route::put('step/1', [CoachOnboardingController::class, 'updateStep1'])->middleware('throttle:30,1')->name('step1');
Route::put('step/2', [CoachOnboardingController::class, 'updateStep2'])->middleware('throttle:30,1')->name('step2');
Route::put('step/3', [CoachOnboardingController::class, 'updateStep3'])->middleware('throttle:30,1')->name('step3');
Route::put('step/4', [CoachOnboardingController::class, 'updateStep4'])->middleware('throttle:30,1')->name('step4');
Route::put('step/5', [CoachOnboardingController::class, 'updateStep5'])->middleware('throttle:30,1')->name('step5');
Route::put('step/6', [CoachOnboardingController::class, 'updateStep6'])->middleware('throttle:30,1')->name('step6');
Route::put('step/7', [CoachOnboardingController::class, 'updateStep7'])->middleware('throttle:30,1')->name('step7');
Route::put('step/8', [CoachOnboardingController::class, 'updateStep8'])->middleware('throttle:30,1')->name('step8');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter CoachOnboardingControllerTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add modules/Users/app/Http/Controllers/Api/V1/CoachOnboardingController.php modules/Users/routes/api.php modules/Users/tests/Feature/Http/Controllers/Api/V1/CoachOnboardingControllerTest.php
git commit -m "feat(onboarding-api): add step 1-8 JSON endpoints"
```

---

## Task 6: API publish endpoint

`POST publish` — enforces completeness gate, sets both flags, returns 422 with `missing_sections` when incomplete.

**Files:**
- Modify: `modules/Users/app/Http/Controllers/Api/V1/CoachOnboardingController.php`
- Modify: `modules/Users/routes/api.php`
- Modify: `modules/Users/tests/Feature/Http/Controllers/Api/V1/CoachOnboardingControllerTest.php`

**Interfaces:**
- Consumes: trait `publishCoach`; `OnboardingPublishRequest`.
- Produces: `POST /api/v1/coaches/me/onboarding/publish` → `200 {data: {coach,...}}` on success; `422 {message, missing_sections}` when incomplete.

- [ ] **Step 1: Write failing tests (complete → published; incomplete → 422)**

Add to `CoachOnboardingControllerTest`:

```php
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

    $response->assertUnprocessable()->assertJsonValidationErrors(['accepted_terms']);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter CoachOnboardingControllerTest`
Expected: FAIL (404 — publish route missing).

- [ ] **Step 3: Add the `publish` method**

Add import `use App\Modules\Users\Http\Requests\OnboardingPublishRequest;` and:

```php
public function publish(OnboardingPublishRequest $request): JsonResponse
{
    $coach = $this->getOrCreateCoach($request);
    $result = $this->publishCoach($coach);

    if (! $result['ok']) {
        return response()->json([
            'message' => 'Completa tutte le sezioni prima di pubblicare.',
            'missing_sections' => $result['missing'],
        ], 422);
    }

    return response()->json(['data' => $this->onboardingStatePayload($coach->fresh())]);
}
```

- [ ] **Step 4: Add the publish route**

In the `onboarding` group:

```php
Route::post('publish', [CoachOnboardingController::class, 'publish'])->middleware('throttle:10,1')->name('publish');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter CoachOnboardingControllerTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add modules/Users/app/Http/Controllers/Api/V1/CoachOnboardingController.php modules/Users/routes/api.php modules/Users/tests/Feature/Http/Controllers/Api/V1/CoachOnboardingControllerTest.php
git commit -m "feat(onboarding-api): add publish endpoint with completeness gate"
```

---

## Task 7: API upload endpoints

Six media endpoints reusing the trait upload helpers. Tests use `Storage::fake` + `UploadedFile::fake` and mock `ObjectStorageService` for the private-storage ones.

**Files:**
- Modify: `modules/Users/app/Http/Controllers/Api/V1/CoachOnboardingController.php`
- Modify: `modules/Users/routes/api.php`
- Modify: `modules/Users/tests/Feature/Http/Controllers/Api/V1/CoachOnboardingControllerTest.php`

**Interfaces:**
- Consumes: trait `storeCoachPhoto`, `storeGalleryPhoto`, `removeGalleryPhoto`, `storeIntroVideo`, `storeCertificationFile`, `storeInsuranceFile`.
- Produces:
  - `POST onboarding/photo` (`photo`, `type`) → `{url}`
  - `POST onboarding/gallery` (`photo`) → `{url, photos}`; 6-cap → 422
  - `DELETE onboarding/gallery` (`media_id`) → `{photos}`; missing → 404
  - `POST onboarding/video` (`video`) → `{url}`
  - `POST onboarding/certification` (`file`, `certification_index`) → `{url}`; missing index → 404
  - `POST onboarding/insurance` (`file`) → `{url}`

- [ ] **Step 1: Write failing tests for photo upload + certification-index-404**

Add to `CoachOnboardingControllerTest` (add imports `use Illuminate\Http\UploadedFile;`, `use Illuminate\Support\Facades\Storage;`, `use App\Modules\Users\Services\ObjectStorageService;`, `use App\Modules\Users\Models\Certification;`):

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter CoachOnboardingControllerTest`
Expected: FAIL (404 — upload routes missing).

- [ ] **Step 3: Add the six upload methods to the controller**

Add `use App\Modules\Users\Services\ObjectStorageService;` and `use App\Modules\Users\Rules\MaxVideoDuration;`:

```php
public function uploadPhoto(Request $request): JsonResponse
{
    $request->validate([
        'photo' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
        'type' => ['required', 'in:profile,cover'],
    ]);
    $coach = $this->getOrCreateCoach($request);
    $url = $this->storeCoachPhoto($coach, $request->file('photo'), $request->input('type'));

    return response()->json(['url' => $url]);
}

public function uploadGalleryPhoto(Request $request): JsonResponse
{
    $request->validate(['photo' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp']]);
    $coach = $this->getOrCreateCoach($request);
    if ($coach->getMedia('gallery')->count() >= 6) {
        return response()->json(['error' => 'Limite massimo di 6 foto raggiunto.'], 422);
    }

    return response()->json($this->storeGalleryPhoto($coach, $request->file('photo')));
}

public function deleteGalleryPhoto(Request $request): JsonResponse
{
    $request->validate(['media_id' => ['required', 'integer']]);
    $coach = $this->getOrCreateCoach($request);
    $result = $this->removeGalleryPhoto($coach, $request->integer('media_id'));

    if ($result === null) {
        return response()->json(['error' => 'Foto non trovata.'], 404);
    }

    return response()->json($result);
}

public function uploadVideo(Request $request): JsonResponse
{
    $request->validate([
        'video' => ['required', 'file', 'max:102400', 'mimes:mp4,mov', new MaxVideoDuration(30)],
    ]);
    $coach = $this->getOrCreateCoach($request);

    return response()->json(['url' => $this->storeIntroVideo($coach, $request->file('video'))]);
}

public function uploadCertification(Request $request, ObjectStorageService $storage): JsonResponse
{
    $request->validate([
        'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        'certification_index' => ['required', 'integer', 'min:0'],
    ]);
    $coach = $this->getOrCreateCoach($request);
    $url = $this->storeCertificationFile($coach, $request->file('file'), $request->integer('certification_index'), $storage);

    if ($url === null) {
        return response()->json(['error' => 'Certificazione non trovata.'], 404);
    }

    return response()->json(['url' => $url]);
}

public function uploadInsurance(Request $request, ObjectStorageService $storage): JsonResponse
{
    $request->validate(['file' => ['required', 'file', 'max:10240', 'mimes:pdf']]);
    $coach = $this->getOrCreateCoach($request);

    return response()->json(['url' => $this->storeInsuranceFile($coach, $request->file('file'), $storage)]);
}
```

- [ ] **Step 4: Add the six upload routes**

In the `onboarding` group:

```php
Route::post('photo', [CoachOnboardingController::class, 'uploadPhoto'])->middleware('throttle:20,1')->name('photo');
Route::post('gallery', [CoachOnboardingController::class, 'uploadGalleryPhoto'])->middleware('throttle:20,1')->name('gallery.store');
Route::delete('gallery', [CoachOnboardingController::class, 'deleteGalleryPhoto'])->middleware('throttle:20,1')->name('gallery.destroy');
Route::post('video', [CoachOnboardingController::class, 'uploadVideo'])->middleware('throttle:10,1')->name('video');
Route::post('certification', [CoachOnboardingController::class, 'uploadCertification'])->middleware('throttle:20,1')->name('certification');
Route::post('insurance', [CoachOnboardingController::class, 'uploadInsurance'])->middleware('throttle:20,1')->name('insurance');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter CoachOnboardingControllerTest`
Expected: PASS.

- [ ] **Step 6: Run the FULL module suite to confirm nothing regressed**

Run: `php artisan test --filter Users` (or `php artisan test modules/Users`)
Expected: PASS — including the existing `OnboardingControllerTest` (web) and `CoachPackageControllerTest`.

- [ ] **Step 7: Commit**

```bash
git add modules/Users/app/Http/Controllers/Api/V1/CoachOnboardingController.php modules/Users/routes/api.php modules/Users/tests/Feature/Http/Controllers/Api/V1/CoachOnboardingControllerTest.php
git commit -m "feat(onboarding-api): add media upload endpoints"
```

---

## Self-Review Notes

- **Spec coverage:** routes (Task 4–7), controller (4–7), trait extraction (1–3), `GET` state shape (4), step contract reuse (5), publish sets both flags + 422 missing_sections (6), uploads (7), tests (every task). Gate stays on `is_published` — no `CoachResource` change (spec §3.5, §6): confirmed no task touches `CoachResource`.
- **Validation envelope:** step/publish use the existing FormRequests → Laravel-default 422 `{message, errors}`; upload endpoints use inline `$request->validate` → same shape. Matches spec §5; mobile `api_exception.dart` handles both. No change needed here.
- **Behavior-preserving guarantee:** Tasks 1–3 each re-run `OnboardingControllerTest` as the gate; Task 7 re-runs the full module suite.
- **Type consistency:** trait method names used in the controller (`persistStep1`..`8`, `publishCoach`, `storeCoachPhoto`, `storeGalleryPhoto`, `removeGalleryPhoto`, `storeIntroVideo`, `storeCertificationFile`, `storeInsuranceFile`, `onboardingStatePayload`, `getOrCreateCoach`) match their definitions in Tasks 1–3.
- **Assumptions to verify during Task 1** (adjust import lines if the repo differs, do not change behavior): the `User` model FQCN (`persistStep1` signature) — match whatever `$request->user()` returns elsewhere in the module; the base `Controller` FQCN (`App\Http\Controllers\Controller`, as used by the existing `CoachController`); Spatie `addMedia(UploadedFile)` availability on `Coach` (the web controller already uses `addMediaFromRequest`, so the media trait is present).
