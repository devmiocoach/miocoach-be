# ClientProgress Module — Design Spec

**Date:** 2026-07-18
**Branch:** `feature/body-metrics-checkins`
**Status:** Approved for planning

## Goal

Add two coach↔client features to Miocoach as a single new module `ClientProgress`:

1. **Body metrics + progress photos** — time-series measurements and photo tracking per client, chartable.
2. **Check-in forms** — coach-authored recurring forms (custom questions) assigned to clients; clients respond; coach gets notified and views history.

One sentence: _a client-progress module that stores time-series body metrics + progress photos and coach-defined recurring check-in forms, exposed via `api/v1` (JWT) and a Filament admin surface._

## Context / conventions this must follow

- Modular via `nwidart/laravel-modules`. Namespace `Modules\ClientProgress\...`. Template = `WorkoutPlans`.
- API auth = custom JWT middleware `jwt.auth` + `verified.email` + `role:coach`/`role:client` (spatie/permission). **Not** Sanctum. Prefix `api/v1`.
- Coach↔Client = m2m pivot `athlete_coach` (`coach_id`, `athlete_id`, `status`). No Laravel Policies anywhere — authorization is **inline ownership checks** in controllers.
- Responses: `response()->json(['data' => XResource::...])`, `JsonResource` (snake_case out). FormRequests validate **camelCase** input.
- Files: `spatie/laravel-medialibrary` installed + used (Coach gallery). Disk `s3` (S3/R2-compatible). Signed URLs via `temporaryUrl()`.
- Enums carry `#[TypeScript]` + implement `HasLabel`; TS output to module `resources/js/types/generated.d.ts` via `module:generate-types`.
- Filament resource split: `Resource.php` + `Schemas/` + `Tables/` + `Pages/`. Module plugin `Modules\ClientProgress\Filament\ClientProgressPlugin` (auto-discovered).
- Cross-module relations bolted onto `Client`/`Coach` via `resolveRelationUsing()` in the module provider — never edit the Users module.
- Push infra does **not** exist (only a logging `StubPushService` in Bookings). DB notifications work (Filament renders them).

## Decisions (locked)

| # | Decision | Choice |
|---|---|---|
| 1 | Module structure | **One module `ClientProgress`** (metrics + photos + check-ins together) |
| 2 | Photo storage / thumbnails | **Spatie MediaLibrary** — collection `photo` on disk `s3`, conversion `thumb` 400×400, signed URLs TTL 1h |
| 3 | Push on check-in submit | **Own `PushServiceContract` + `StubPushService` (log) + DB notification** now; real FCM deferred |
| 4 | Scope | **Both features complete** in one spec/plan |
| 5 | Check-in scheduling (#6) | **Build minimal** hourly dispatch command; gaps recorded in `modules_todo` |

## Data model (single shared DB)

### `body_metrics`
One row per logging event. Chartable values as columns (no JSON blob) so `GET ?metric=` maps to one column.

```
id
client_id        FK → clients, cascadeOnDelete
logged_by        enum [coach, client]
weight_kg        decimal(6,2) nullable
body_fat_pct     decimal(5,2) nullable
waist_cm         decimal(5,1) nullable
chest_cm         decimal(5,1) nullable
hips_cm          decimal(5,1) nullable
arm_cm           decimal(5,1) nullable
thigh_cm         decimal(5,1) nullable
calf_cm          decimal(5,1) nullable
notes            text nullable
measured_at      timestamp
timestamps
index (client_id, measured_at)
```

The spec's nested `measurements: { waistCm, ... }` is flattened into columns on input mapping.

### `progress_photos`
`ProgressPhoto implements HasMedia` (`InteractsWithMedia`).

```
id
client_id   FK → clients, cascadeOnDelete
logged_by   enum [coach, client]
taken_at    timestamp
timestamps
index (client_id, taken_at)
```

- Media collection `photo` (single file), disk `s3`, `->useDisk('s3')`.
- Conversion `thumb` = 400×400 (`fit(Manipulations::FIT_CROP, 400, 400)`).
- No manual `s3_key`/`thumbnail_s3_key` columns — the media table holds paths.
- `fullUrl` = original signed 1h; `thumbUrl` = `thumb` conversion signed 1h.

### `checkin_forms`
Coach-owned template.

```
id
coach_id     FK → coaches, cascadeOnDelete
title        string
frequency    enum [daily, weekly, biweekly, monthly]
send_time    time
questions    json     // [{ id, text, type, required, order }]
is_active    boolean default true
timestamps
```

`questions` is a JSON array cast (MVP — no separate questions table). `type` ∈ `QuestionType`.

### `checkin_form_client` (assignment pivot)
```
id
checkin_form_id  FK → checkin_forms, cascadeOnDelete
client_id        FK → clients, cascadeOnDelete
assigned_at      timestamp
timestamps
unique (checkin_form_id, client_id)
```

### `checkin_responses`
```
id
checkin_form_id  FK → checkin_forms, cascadeOnDelete
client_id        FK → clients, cascadeOnDelete
answers          json    // [{ questionId, value }]
submitted_at     timestamp
timestamps
index (checkin_form_id, client_id, submitted_at)
```

## Enums (all `#[TypeScript]`, `implements HasLabel`)

- `MetricType`: `weight`, `body_fat`, `waist`, `chest`, `hips`, `arm`, `thigh`, `calf` — plus a `column()` helper mapping each case to its DB column for the `GET ?metric=` query.
- `LoggedBy`: `coach`, `client`.
- `CheckinFrequency`: `daily`, `weekly`, `biweekly`, `monthly`.
- `QuestionType`: `text`, `number`, `rating`, `yes_no`.

## Models

- `BodyMetric` — `belongsTo(Client)`, enum casts, `#[Scope]` `forClient`.
- `ProgressPhoto` — `belongsTo(Client)`, `HasMedia`, `registerMediaCollections()`, `registerMediaConversions()`.
- `CheckinForm` — `belongsTo(Coach)`, `belongsToMany(Client, 'checkin_form_client')` (assigned clients), `hasMany(CheckinResponse)`, `questions` array cast, enum casts, scope `active`.
- `CheckinResponse` — `belongsTo(CheckinForm)`, `belongsTo(Client)`, `answers` array cast.

Factories + seeders for each (per Laravel conventions).

## API (`api/v1`, JWT + `verified.email`)

### Client-data endpoints — inline guard `userCanAccessClient($user, $client)`
Access allowed if: user is a **coach linked to the client** via `athlete_coach` (active), **OR** user **is** that client (`$user->client?->id === $client->id`). Else 403. Per-route `throttle`.

| Method | Route | Request → behavior |
|---|---|---|
| POST | `clients/{client}/metrics` | `StoreBodyMetricRequest` (camelCase: `weightKg`, `bodyFatPct`, `measurements.waistCm`…, `notes`, `loggedBy`) → create `BodyMetric` (`measured_at = now()`). Returns `BodyMetricResource`. |
| POST | `clients/{client}/metrics/photos` | multipart `photo` `required|image|mimes:jpg,jpeg,png|max:10240` (10 MB), `loggedBy`, optional `takenAt` → create `ProgressPhoto`, attach media (thumb auto-generated). Returns `ProgressPhotoResource`. |
| GET | `clients/{client}/metrics?metric=&from=&to=` | validate `metric ∈ MetricType`; select `measured_at as date`, `<column> as value`, non-null, ordered → `[{date, value}]` via `MetricPointResource`. |
| GET | `clients/{client}/metrics/photos?from=&to=` | list `ProgressPhoto` in range → `ProgressPhotoResource` with `thumbUrl` + `fullUrl` signed 1h. |

### Coach-scoped (`role:coach`, `$coach = $request->user()->coach` else 403)

| Method | Route | Behavior |
|---|---|---|
| POST | `coaches/me/checkin-forms` | `StoreCheckinFormRequest` (`title`, `frequency`, `sendTime`, `questions[]`, `clientIds[]`) → create form, immediate assign `clientIds` (validate each is coach's client). `CheckinFormResource`. |
| GET | `coaches/me/checkin-forms` | list coach's forms. |
| PUT | `coaches/me/checkin-forms/{form}` | ownership guard → update. |
| POST | `coaches/me/checkin-forms/{form}/assign` | `clientIds[]` → sync/attach (validate ownership). |
| GET | `coaches/me/clients/{client}/checkins?from=&to=` | ownership guard → responses in range, questions expanded (join form `questions` to each answer). `CheckinResponseResource`. |

### Client-scoped (`role:client`)

| Method | Route | Behavior |
|---|---|---|
| POST | `checkin-forms/{form}/respond` | guard: form assigned to this client. `RespondCheckinRequest` (`answers[]` = `{questionId, value}`, validate required questions present) → create `CheckinResponse` (`submitted_at = now()`) → dispatch `ClientSubmittedCheckinNotification` to the form's coach. `CheckinResponseResource`. |

### API Resources
`BodyMetricResource`, `MetricPointResource` (`{date, value}`), `ProgressPhotoResource` (`id, takenAt, loggedBy, thumbUrl, fullUrl`), `CheckinFormResource`, `CheckinResponseResource` (with expanded questions).

## Notification (stub push + DB)

- `Modules\ClientProgress\Notifications\ClientSubmittedCheckinNotification` → `via() = ['database']` (Filament renders) **and** calls injected `PushServiceContract::send()`.
- Define module-owned `Modules\ClientProgress\Services\Contracts\PushServiceContract` + `StubPushService` (logs), bound in provider (mirror Bookings pattern, but ClientProgress-owned).
- Recipient = the coach who owns the form. Message: `"[Nome] ha compilato il check-in"`.

## Scheduled dispatch (minimal — #6)

- Command `clientprogress:dispatch-checkins`, scheduled **hourly** (registered in module RouteServiceProvider/boot or a console schedule).
- For each `is_active` form, for each assigned client: if `send_time` hour matches current hour **and** the frequency period is due (daily = every day; weekly = same weekday as `assigned_at`; biweekly = every 2nd such weekday; monthly = same day-of-month) → send a reminder notification to the **client** (DB + stub push): "È ora del tuo check-in: [title]".
- Idempotency: guard against double-send within the same period using the last `checkin_responses`/a lightweight `checkin_dispatches` marker **OR** (MVP) accept that hourly cron + hour-match already limits to once/hour; dedupe refinement is a `modules_todo` item.

### `modules_todo` (gaps to record)
Create `modules/ClientProgress/MODULE_TODO.md` listing what minimal scheduling defers:
- Real push transport (FCM/Expo): device-token table + driver + channel. Currently `StubPushService` logs only.
- Dispatch idempotency/dedupe table (`checkin_dispatches`) to guarantee once-per-period even under retries/multiple workers.
- Timezone handling: `send_time` is evaluated in app timezone; per-client/per-coach timezone not yet honored.
- Missed-window catch-up (if cron skips an hour) not handled.
- `r2_docs` disk still undefined in `config/filesystems.php`; photos use `s3`.

## Filament (coach `/admin`)

Nav group **"Client Progress"** via `ClientProgressPlugin`. Resources (split Resource/Schemas/Tables/Pages):

- `CheckinFormResource` — form builder: `Repeater` for `questions` (text + `Select` type + `Toggle` required + order), `frequency` Select, `send_time` TimePicker, assign action for `clientIds`.
- `CheckinResponseResource` — read-only history table (form, client, submitted_at), infolist expands answers.
- `ProgressPhotoResource` — gallery via `filament/spatie-laravel-media-library-plugin`, client filter.
- `BodyMetricResource` — table with per-client filter + date range; metric columns.

## Cross-module wiring (module provider)

```php
Client::resolveRelationUsing('bodyMetrics', fn ($c) => $c->hasMany(BodyMetric::class));
Client::resolveRelationUsing('progressPhotos', fn ($c) => $c->hasMany(ProgressPhoto::class));
Client::resolveRelationUsing('checkinResponses', fn ($c) => $c->hasMany(CheckinResponse::class));
Client::resolveRelationUsing('assignedCheckinForms', fn ($c) => $c->belongsToMany(CheckinForm::class, 'checkin_form_client'));
Coach::resolveRelationUsing('checkinForms', fn ($c) => $c->hasMany(CheckinForm::class));
```

## Not doing (YAGNI)

- No health-data encryption (numeric metrics must chart; `ClientAnamnesis` encryption pattern intentionally skipped).
- No real FCM/APNs/Expo now.
- No separate `checkin_questions` table (JSON on form).
- No API v2.

## Testing

- **Feature (API):** each endpoint — happy path, auth-fail (no JWT), ownership-denied (403 for unrelated coach/client), validation errors. Photo upload size/mime rejection. `GET metrics` returns `[{date,value}]` shape. Respond dispatches `ClientSubmittedCheckinNotification` (Notification::fake).
- **Command:** `clientprogress:dispatch-checkins` sends for due form, skips non-due.
- **Filament:** resource list/create render + create form validation.
- Run: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='^Modules\ClientProgress\Tests'`.

## Post-build steps

- `composer dump-autoload && php artisan module:enable ClientProgress`
- `php artisan module:generate-types ClientProgress`
- `php artisan module:migrate ClientProgress --seed`
- `vendor/bin/pint --dirty` ; `npm run build`
