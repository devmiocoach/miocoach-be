# Coach Onboarding — Mobile JSON API (backend sub-project)

**Date:** 2026-07-18
**Repo:** `miocoach-be` (Laravel, modular — `modules/Users`)
**Status:** Design approved, pending implementation plan
**Part of:** the cross-repo "coach onboarding wizard" initiative. This is
sub-project 1 (backend). Sub-project 2 (Flutter 9-step wizard in
`miocoach_mobile`) depends on the contract defined here and gets its own
spec/plan cycle afterward.

---

## 1. Problem

The coach onboarding 9-step wizard exists **only as a web (Inertia) flow**
(`modules/Users/app/Http/Controllers/OnboardingController.php`, routes
`coach.onboarding.*` in `web.php`). The mobile app has no equivalent API. The
existing mobile coach endpoints (`PATCH coaches/me`, flat `publish`, availability
replace, packages CRUD, single-file certification) use a **different, flatter
coach model** than the web wizard and do not cover the full step schema, media
uploads, the specialization pivot, service radius, subscription tiers, gallery,
policies, or `onboarding_completed_at`.

Goal: expose the web wizard's exact behavior as a JSON API the Flutter client
can drive for **full 9-step parity**, producing a coach profile the existing web
completeness/publish gate considers complete — without touching the existing flat
mobile API or the web wizard.

## 2. Approach (decided)

**Mirror the web `OnboardingController` as a dedicated JSON API controller**,
reusing the same FormRequests and business logic, returning JSON instead of
Inertia. Entirely additive. Shared logic (currently private methods on the web
controller) is **extracted into a trait** so web and API share one source of
truth and cannot drift.

Rejected alternatives: extending the flat `PATCH coaches/me` (forces reconciling
two coach models + two publish gates, risks breaking existing web+mobile);
hybrid reuse-plus-gaps (leaves the client juggling two slot/package shapes and
two publish semantics).

## 3. Scope

### 3.1 New route group

In `modules/Users/routes/api.php`, inside the existing
`Route::prefix('coaches/me')->middleware('role:coach')` group (so it inherits
`jwt.auth` + `verified.email` + `role:coach`), add:

| Method | Path | Controller method | Request/validation |
|---|---|---|---|
| GET | `coaches/me/onboarding` | `show` | — |
| PUT | `coaches/me/onboarding/step/1` | `updateStep1` | `OnboardingStep1Request` |
| PUT | `coaches/me/onboarding/step/2` | `updateStep2` | `OnboardingStep2Request` |
| PUT | `coaches/me/onboarding/step/3` | `updateStep3` | `OnboardingStep3Request` |
| PUT | `coaches/me/onboarding/step/4` | `updateStep4` | `OnboardingStep4Request` |
| PUT | `coaches/me/onboarding/step/5` | `updateStep5` | `OnboardingStep5Request` |
| PUT | `coaches/me/onboarding/step/6` | `updateStep6` | `OnboardingStep6Request` |
| PUT | `coaches/me/onboarding/step/7` | `updateStep7` | `OnboardingStep7Request` |
| PUT | `coaches/me/onboarding/step/8` | `updateStep8` | `OnboardingStep8Request` |
| POST | `coaches/me/onboarding/publish` | `publish` | `OnboardingPublishRequest` |
| POST | `coaches/me/onboarding/photo` | `uploadPhoto` | inline (`photo`, `type`) |
| POST | `coaches/me/onboarding/gallery` | `uploadGalleryPhoto` | inline (`photo`) |
| DELETE | `coaches/me/onboarding/gallery` | `deleteGalleryPhoto` | inline (`media_id`) |
| POST | `coaches/me/onboarding/video` | `uploadVideo` | inline (`video`, `MaxVideoDuration(30)`) |
| POST | `coaches/me/onboarding/certification` | `uploadCertification` | inline (`file`, `certification_index`) |
| POST | `coaches/me/onboarding/insurance` | `uploadInsurance` | inline (`file`) |

Throttle each consistently with sibling `coaches/me/*` routes (e.g. writes
`throttle:30,1`, uploads `throttle:20,1`, publish `throttle:10,1`).

Route names under `coaches.me.onboarding.*`.

### 3.2 New controller

`modules/Users/app/Http/Controllers/Api/V1/CoachOnboardingController.php`.

- One method per web controller method, same signatures, but every method
  returns a JSON response (`{data: ...}`), never `redirect()`/`Inertia::render`.
- Step methods: run the same persistence as the web controller, then return
  `{data: {coach: OnboardingCoachResource, completed_steps: [...]}}`.
- `show`: returns the full onboarding state (see 3.4).
- Upload methods: same validation + storage as the web controller; return the
  URL(s) as the web versions do (`{url}` / `{url, photos}` / `{photos}`).
- `publish`: enforce the completeness gate (`resolveIncompleteSections`); on
  failure return **422** `{message, missing_sections: [...]}`. On success set
  `is_published=true` AND `onboarding_completed_at=now()`, return
  `{data: OnboardingCoachResource}`.

### 3.3 Shared trait (extract, no logic drift)

Extract from the web `OnboardingController` into a trait, e.g.
`modules/Users/app/Concerns/HandlesCoachOnboarding.php` (or an equivalent
service): `getOrCreateCoach`, `resolveCompletedSteps`, `resolveIncompleteSections`,
and the per-step persistence bodies where practical (specialization sync,
availability rebuild, packages replace, certification metadata create, policy
update). Web controller and API controller both `use` it. Web controller keeps
its Inertia responses; API controller wraps the same mutations in JSON.

The reused FormRequests (`OnboardingStep1Request`…`Step8Request`,
`OnboardingPublishRequest`) are unchanged — they are pure validators; the
redirect side-effects live in the web controller, not the requests.

### 3.4 `GET coaches/me/onboarding` response

New `OnboardingStateResource` (or plain array in the controller):

```json
{
  "data": {
    "coach": {
      "display_name": "…", "tagline": "…", "bio": "…",
      "mode": "hybrid", "languages": ["it","en"],
      "specialization_ids": [3, 7],
      "certifications": [{"id":1,"name":"…","issuer":"…","issued_at":"2020-01-01","file_url":"…"}],
      "p_iva": "…", "has_insurance": true, "insurance_company": "…",
      "insurance_expiry": "2027-01-01", "insurance_file_url": "…",
      "availability": [{"id":1,"day_of_week":1,"start_time":"09:00","end_time":"12:00","duration_minutes":60}],
      "session_duration_default": "60", "cancellation_window_hours": 24,
      "city": "…", "lat": 45.4, "lng": 9.1, "service_radius_km": 20,
      "available_at_home": true, "partner_gyms": ["…"],
      "single_session_price_cents": 5000,
      "monthly_subscription_cents": null, "quarterly_subscription_cents": null,
      "packages": [{"id":1,"name":"…","sessions_count":10,"price_cents":45000,"sort_order":0}],
      "gallery_photos": ["…"], "intro_video_url": "…",
      "profile_photo_url": "…", "cover_photo_url": "…",
      "no_show_fee_cents": null, "late_tolerance_minutes": 15,
      "free_first_session": false, "welcome_discount_enabled": false,
      "welcome_discount_percent": null, "custom_terms": null,
      "is_published": false, "onboarding_completed_at": null
    },
    "completed_steps": [1, 2],
    "specializations": [{"id":3,"name":"…","slug":"…"}]
  }
}
```

`specializations` = the step-2 picker catalog: all non-custom specializations
plus any custom ones already linked to this coach (matches the web `index`
payload). `specialization_ids` = the coach's currently linked pivot IDs.

### 3.5 CoachResource (mobile gate)

The router gate is **`is_published`**, already returned by the existing
`CoachResource` (`GET coaches/me`). **No change required for gating.**
`onboarding_completed_at` is intentionally NOT added to `CoachResource` — the
onboarding-specific state comes from `GET coaches/me/onboarding`. (If a future
need arises to distinguish "published" from "completed onboarding", expose
`onboarding_completed_at` in `CoachResource` then; out of scope now.)

## 4. Exact step contract (reused, unchanged)

Validation is whatever the existing FormRequests already enforce. Summarized for
the client team; the FormRequests are the source of truth.

- **Step 1 — Profile:** `display_name` req 2–100, `tagline` req ≤120, `bio` req
  10–500. Side effect: also sets `users.name = display_name`.
- **Step 2 — Specializations:** `specialization_ids[]` (≤10, exists) OR
  `custom_specializations[]` (≤5, ≤60 each) — one required; `languages[]` req
  ≥1; `mode` req in `online|in_person|hybrid`. Custom specializations are
  `firstOrCreate`d (slug, `is_custom=true`, `created_by=coach.user_id`) then the
  pivot is `sync`ed.
- **Step 3 — Credentials:** `certifications[]` (≤20; each `name` req ≤150,
  `issuer` ≤100, `year` `\d{4}`), `p_iva` (11 digits + `ValidPIva`),
  `has_insurance` req bool, `insurance_company`/`insurance_expiry`
  required_if has_insurance. Cert files uploaded later by index.
  **Client contract gotcha:** step 3 does `certificationRecords()->delete()` then
  recreates every cert with empty `file_url`/`storage_path`. Each step-3 save
  therefore wipes all previously-uploaded cert files and resets positional index
  ordering. The client MUST upload cert files (`POST onboarding/certification`,
  by `certification_index`) only AFTER the final step-3 save, and must treat any
  step-3 re-submit as invalidating all prior cert uploads.
- **Step 4 — Availability:** `slots[]` req ≥1 (`day_of_week` 0–6,
  `start_time`/`end_time` `H:i`), `session_duration_default` in `45|60|90`,
  `cancellation_window_hours` in `12|24|48`. Slots with `end<=start` skipped;
  each stored `duration_minutes=session_duration_default`, `is_recurring=true`.
- **Step 5 — Location:** `city` ≤100, `lat` −90..90, `lng` −180..180,
  `service_radius_km` req 1–50, `available_at_home` req bool, `partner_gyms[]`
  (≤20, ≤150 each).
- **Step 6 — Pricing:** `single_session_price_cents` req ≥100, `packages[]`
  (≤10; each `name` req, `sessions_count` req 2–100, `price_cents` req ≥100),
  `monthly_subscription_cents`/`quarterly_subscription_cents` nullable ≥100.
  Packages fully replaced (delete + recreate, `is_active=true`,
  `sort_order=index`).
- **Step 7 — Gallery:** `gallery_photos[]` (≤6 URLs), `has_video` bool
  (validated, not persisted). Actual media via upload endpoints.
- **Step 8 — Policies:** `no_show_fee_cents` ≥0, `late_tolerance_minutes` in
  `10|15|20|30`, `free_first_session` req bool, `welcome_discount_enabled` req
  bool, `welcome_discount_percent` required_if enabled 1–100, `custom_terms`
  ≤2000.
- **Publish:** `accepted_terms` req accepted. Completeness gate requires:
  `display_name`; `mode` + ≥1 specialization pivot record; `cancellation_window_hours`
  + ≥1 availability; `single_session_price_cents`. On success →
  `is_published=true`, `onboarding_completed_at=now()`.

### Upload endpoints (inline validation, mirror web)

- **photo:** `photo` image ≤2MB jpg/jpeg/png/webp; `type` in `profile|cover`.
  Spatie collection `profile_photo`/`cover_photo`; writes
  `profile_photo_url`/`cover_photo_url`; returns `{url}`.
- **gallery:** `photo` same rules; max 6 in `gallery` (else 422); returns
  `{url, photos[]}`; syncs `gallery_photos`.
- **delete gallery:** `media_id` int; returns `{photos[]}`.
- **video:** `video` file ≤100MB mp4/mov + `MaxVideoDuration(30)`; collection
  `intro_video`; writes `intro_video_url` + `intro_video_storage_path`; `{url}`.
- **certification:** `file` ≤10MB pdf/jpg/jpeg/png + `certification_index` int;
  attaches to Nth `certificationRecords()` via `ObjectStorageService` (private,
  `certifications/{coach_id}`); `{url}`.
- **insurance:** `file` ≤10MB pdf; private `coaches/{coach_id}/insurance`;
  writes `insurance_file_url` + `insurance_file_storage_path`; `{url}`.

## 5. Response & error conventions

- Success envelope: `{data: ...}` (matches the module's REST style).
- Validation failures from the reused FormRequests (thrown `ValidationException`)
  are wrapped by the app's global exception handler into the module's custom
  envelope: **422 `{error:{code:VALIDATION_ERROR, message, fields:{field:[msg]}}}`**
  — NOT Laravel-default `{message, errors}`. This is the same shape the rest of
  the module's API emits; the mobile client's `core/network/api_exception.dart`
  parses `error.fields`. (Verified empirically during implementation — the
  earlier draft of this note claiming Laravel-default was incorrect.)
- Publish **completeness** failure is different: it is a manual
  `response()->json(..., 422)` from the controller, NOT a thrown
  `ValidationException`, so the global handler does not touch it — it stays
  exactly `{message, missing_sections: [...]}`. So the publish endpoint returns
  two distinct 422 shapes: `error.fields` for the `accepted_terms` validation
  failure, `{message, missing_sections}` for the completeness gate.
- Auth/role failures: standard Laravel `{message}` (401/403), as elsewhere.

## 6. Out of scope

- The Flutter wizard (separate sub-project/spec in `miocoach_mobile`).
- Reconciling the two coach models (pivot vs JSON specializations,
  `single_session_price_cents` vs `price_per_session`). The onboarding API
  follows the **web** model; the existing flat `PATCH coaches/me` is untouched.
- Any change to the web wizard's behavior (only refactor-extract of shared
  helpers into a trait, behavior-preserving).
- Exposing `onboarding_completed_at` in `CoachResource`.

## 7. Testing

Feature tests under `modules/Users/tests/Feature` (mirror existing coach API
tests):
- Each step endpoint: happy path persists correct columns/relations; validation
  failures return 422; auth (non-coach → 403, unverified email → 403).
- `show`: returns coach state + correct `completed_steps` + specialization catalog.
- Uploads: valid file stored + URL written; oversize/wrong-mime rejected; gallery
  6-photo cap; cert index resolution.
- Publish: incomplete profile → 422 `missing_sections`; complete → sets both
  `is_published` and `onboarding_completed_at`; requires `accepted_terms`.
- Trait extraction is behavior-preserving: existing web onboarding tests still
  pass unchanged.

## 8. Open items for the mobile sub-project (not blocking this one)

- Router gate wiring in `miocoach_mobile` (`is_published` false → `/onboarding`).
- Client models/repository/viewmodels/step widgets for the 9 steps.
- Multipart upload handling via Dio for the 6 upload endpoints.
