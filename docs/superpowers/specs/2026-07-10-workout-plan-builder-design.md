# Workout Plan Builder + Exercise Library — Design

**Date:** 2026-07-10
**Module:** `WorkoutPlans`
**Branch:** `wa-plans`
**Status:** Approved, ready for implementation planning

## Summary

Build the coach-facing web UI for the `WorkoutPlans` module: a three-column drag-and-drop plan builder and a standalone exercise library. The backend models, migrations, and JWT API already exist; this work adds the Inertia surface, extracts persistence into a shared service, and cleans up dead schema.

AI load progression is explicitly **out of scope** — it gets its own spec.

## Scope

| In scope | Out of scope |
| --- | --- |
| Exercise library page (`/coach/exercises`) with CRUD for custom exercises | Native video upload (S3, transcoding) |
| Plan builder page (`/coach/plans/{id}/edit`) | AI load progression (`CalculateAiProgressionJob` stays a stub) |
| Shared `ExerciseLibrary` component | Multi-week periodization |
| Schema cleanup migration | Multi-tab edit locking |
| `WorkoutPlanWriter` service, policies, video host allowlist | Changes to the athlete-facing mobile API surface, beyond the two fixes below |

Two fixes land on the existing API because they are correctness issues, not features:

1. Video URL host allowlist on `Api\V1\ExerciseController@store|update` (currently accepts any string).
2. `Api\V1\WorkoutPlanController@duplicate` forces `is_template = true` on the copy; it must inherit from the source.

## Existing state

The module already has:

- Models: `Exercise`, `WorkoutPlan`, `WorkoutDay`, `WorkoutExercise`, `PlanAssignment`, `WorkoutSession`, `SessionSet`, `PersonalRecord`
- A complete JWT API under `/api/v1/*` (`jwt.auth` + `verified.email`), used by the mobile app
- Filament admin CRUD for exercises, plans, and (read-only) sessions
- Enums `ExerciseCategory`, `ExerciseDifficulty`, `WorkoutGoal`, all `#[TypeScript]`
- One Inertia page: `pages/Index.vue`, a stats landing page on `AppLayout`

It does not have: any coach-facing plan or exercise UI, `app/Services/`, `app/Policies/`, any drag-and-drop dependency, or any AI progression logic.

`workout_exercises` already carries every field the builder needs: `sets`, `reps_target`, `weight_kg`, `rir_target`, `rest_seconds`, `notes`, `order_index`, `workout_day_id`.

## Architecture

### Two surfaces, one service

`/api/v1/*` stays as it is: JWT, mobile. `/coach/*` is new: session `auth` + `role:coach`, Inertia.

Plan persistence moves into `Modules\WorkoutPlans\Services\WorkoutPlanWriter` — the first resident of `app/Services/`. Both `Api\V1\WorkoutPlanController` and the new `Coach\PlanBuilderController` call it. The service knows nothing about HTTP.

Rejected: adding a session guard to the v1 API (touches routes the mobile app depends on in production), and minting a JWT into the Inertia page props (puts a bearer token in `window.__INERTIA_PAGE__`, readable by any XSS, with no `httpOnly` protection).

### Web routes

All under `Route::middleware(['auth', 'role:coach'])->prefix('coach')` in `modules/WorkoutPlans/routes/web.php`.

| Method | URI | Name | Purpose |
| --- | --- | --- | --- |
| GET | `/coach/plans` | `coach.plans.index` | Plan list + stat cards |
| GET | `/coach/plans/create` | `coach.plans.create` | Empty builder |
| GET | `/coach/plans/{plan}/edit` | `coach.plans.edit` | Populated builder |
| POST | `/coach/plans` | `coach.plans.store` | First save; returns the new id |
| PUT | `/coach/plans/{plan}` | `coach.plans.update` | Auto-save (whole tree) |
| POST | `/coach/plans/{plan}/duplicate` | `coach.plans.duplicate` | |
| DELETE | `/coach/plans/{plan}` | `coach.plans.destroy` | |
| GET | `/coach/exercises` | `coach.exercises.index` | Standalone library |
| POST | `/coach/exercises` | `coach.exercises.store` | Create custom exercise |
| PUT | `/coach/exercises/{exercise}` | `coach.exercises.update` | |
| DELETE | `/coach/exercises/{exercise}` | `coach.exercises.destroy` | |

`store` and `update` on plans return JSON, not a redirect. The auto-save must not remount the page.

The existing `GET /workout-plans` (`workoutplans.index`) is replaced by `coach.plans.index`. The current `pages/Index.vue` becomes `pages/Coach/Plans/Index.vue`, wrapped in `CoachLayout` instead of `AppLayout`, keeping its two stat cards above the new plan table.

### Authorization

Ownership checks currently live inline in controllers. This work introduces two policies:

- `WorkoutPlanPolicy` — `view`/`update`/`delete`/`duplicate` require `plan.coach_id === user.coach.id`.
- `ExercisePolicy` — `update`/`delete` require `exercise.coach_id !== null && exercise.coach_id === user.coach.id`. Global exercises (`coach_id === null`) are read-only for coaches.

Both policies grant everything to users with the `admin` role via a `before()` hook. Filament v5 applies registered policies to its resources, so without this hook, introducing `ExercisePolicy` would lock admins out of the twelve seeded global exercises they currently manage through `ExerciseResource`. A feature test covers admin access to a global exercise through Filament.

The API controllers are refactored to call the policies instead of their inline checks, so both surfaces share one rule.

## Data model changes

One migration: `2026_07_10_000001_cleanup_workout_plans_schema`.

### `workout_exercises`

Two columns are dead duplicates. `order` (legacy flat ordering) is superseded by `order_index` (per-day ordering). `reps` (legacy) is superseded by `reps_target`. Both appear only in `$fillable` and `$casts` — no controller, resource, or Filament schema reads them.

```php
DB::table('workout_exercises')
    ->whereNull('reps_target')->whereNotNull('reps')
    ->update(['reps_target' => DB::raw('reps')]);

$table->dropColumn(['order', 'reps']);
```

Corresponding cleanup in `WorkoutExercise::$fillable` and `$casts`.

### `workout_plans.client_id`

Dead. `plan_assignments` is the only assignment path actually read: `ClientWorkoutController@plans` queries it, `WorkoutPlanController@show` authorizes through it, `@assign` writes it. Nothing reads `workout_plans.client_id`.

It also contradicts the design — a nullable one-to-one column next to a many-to-many table with `active_from` and a unique constraint.

Before dropping, rescue any assignment that exists only as a `client_id`. The backfill runs through the query builder rather than raw SQL, because the test suite runs on SQLite and production on MySQL — `NOW()` does not exist in SQLite.

```php
DB::table('workout_plans')->whereNotNull('client_id')->orderBy('id')
    ->chunkById(100, function ($plans) {
        foreach ($plans as $plan) {
            DB::table('plan_assignments')->updateOrInsert(
                ['workout_plan_id' => $plan->id, 'client_id' => $plan->client_id],
                ['assigned_at' => $plan->created_at ?? now(), 'created_at' => now(), 'updated_at' => now()],
            );
        }
    });

$table->dropConstrainedForeignId('client_id');
```

Using `DB::table` rather than Eloquent means soft-deleted plans are included, which is what we want — their assignments should survive too.

`WorkoutPlan::client()` and the `client_id` entries in `$fillable` are removed.

### `workout_exercises.exercise_id` foreign key

Currently `cascadeOnDelete`: deleting a custom exercise silently shortens every plan using it. Changed to `restrictOnDelete`. `ExercisePolicy`-authorized deletes then check usage first and respond `422` with the count of plans using it.

This changes the mobile API contract: `DELETE /api/v1/coaches/me/exercises/{exercise}` can now fail with 422. Accepted deliberately — silent data loss is worse than a handled error.

### What stays

`week_number` on `workout_exercises` stays (nullable, always null from the web builder; the mobile API exposes it). `duration_weeks` on `workout_plans` stays as a descriptive field in the settings panel. Multi-week periodization is a different data model and gets its own spec.

No new columns. No `status` column — see "Draft state" below.

## Plan lifecycle

The `workout_plans` row is created on the **first meaningful save**, not on page open.

`/coach/plans/create` loads with `planId = null`. The first save — whether auto-triggered or from the Save button — issues `POST`, receives the id, and every subsequent save issues `PUT`. A save only fires when there is something to save: a non-empty name **or** at least one exercise. Opening and abandoning the page leaves no row behind.

Rejected: creating the row on page open and redirecting to `/edit` (leaves empty plans in the database on every accidental visit, requiring a cleanup job), and a `draft`/`published` status column (the badge in the design describes *unsaved browser changes*, not a property of the plan — two different things with the same name).

### Draft state

The "Bozza / Salvato" badge is client-side `isDirty`, not a database column. `Salvato HH:mm` shows `lastSavedAt` from the most recent successful save.

## Save protocol

The builder sends the **whole tree** on every save: plan attributes, days, exercises. The server upserts in a transaction.

`WorkoutPlanWriter::write(Coach $coach, ?WorkoutPlan $plan, PlanTreeData $data): WorkoutPlan`

1. `updateOrCreate` the plan (`name`, `description`, `goal`, `difficulty`, `duration_weeks`, `sessions_per_week`, `is_template`).
2. Days: upsert by `id` where present in the payload; days absent from the payload are deleted (cascading to their exercises).
3. Exercises: upsert by `id` within each day; absent ones deleted. `order_index` is the array position.
4. Assignments: `plan_assignments` synced against the client multi-select — `updateOrCreate` for added, delete for removed. **Only on "Salva e Assegna"**, never on auto-save.

Upsert-by-id rather than delete-and-reinsert, so `workout_exercises.id` survives saves. New cards send `id: null`.

Rejected: granular endpoints (`POST /days`, `PATCH /exercises/{id}`, `POST /reorder`) — eight to ten new routes, race conditions between reorder and inline edit, and auto-save loses its meaning. And explicit-save-only — a coach spends twenty minutes on this screen; losing it to a closed tab is unacceptable.

Payload size is not a concern: a 30-exercise plan is roughly 5KB.

### `PlanTreeData`

`spatie/laravel-data` is **not** installed in this project and is not being added. `spatie/laravel-typescript-transformer` **is**, and its configured `DtoTransformer` transforms plain PHP classes annotated `#[TypeScript]` with typed public properties.

So `PlanTreeData`, `PlanDayData`, and `PlanExerciseData` are plain readonly classes under `Modules\WorkoutPlans\Data\`, each `#[TypeScript]`. The TypeScript type of the save payload is generated from them via `php artisan module:generate-types WorkoutPlans` — no hand-written interfaces on two sides of the wire.

### Validation

`StoreCoachPlanRequest` and `UpdateCoachPlanRequest`:

- `days` max 7; `days.*.exercises` max 50
- `exercises.*.sets` 1–20; `reps_target` 1–100; `weight_kg` 0–999.99, two decimals; `rir_target` 0–10; `rest_seconds` 0–3600
- `exercises.*.exercise_id` must exist **and be visible to this coach** — `Rule::exists` scoped to `coach_id IS NULL OR coach_id = :coachId`. Without this, a coach can put another coach's custom exercise into a plan.
- `client_ids.*` must be athletes of this coach — the relation is many-to-many through the `athlete_coach` pivot (`Coach::athletes()`), not a `clients.coach_id` column.

### Video host allowlist

`App\Rules\AllowedVideoHost`, applied in `Coach\ExerciseController` and in the existing `Api\V1\ExerciseController@store|update`.

Allowed hosts come from `modules/WorkoutPlans/config/config.php`: `youtube.com`, `www.youtube.com`, `youtu.be`, `vimeo.com`, `player.vimeo.com`. Scheme must be `https`.

`thumbnail_url` is derived server-side from the provider's oEmbed endpoint at save time. If the oEmbed call fails, `thumbnail_url` stays `null` and the card shows a placeholder — a missing thumbnail never blocks a save.

Video preview renders as an `<iframe>` to an allowlisted provider, never a `<video>` element pointed at a user-supplied URL.

Native upload (presigned S3 `PUT`, progress bar, ffmpeg thumbnails) is deferred to its own spec. For a ten-second exercise demo, pasting a link is a small friction and zero infrastructure.

## Frontend

### Pages

Under `modules/WorkoutPlans/resources/js/pages/Coach/`, all wrapped in `CoachLayout`:

- `Plans/Index.vue` — stat cards + plan table
- `Plans/Builder.vue` — the three-column builder
- `Exercises/Index.vue` — exercise grid + filters

Tables follow the established convention from `modules/Users/.../Coach/Clients/Index.vue`: a hand-rolled semantic `<table>` inside a `Card`, `@/components/ui/*` primitives, `~icons/heroicons/*`, `$t()` for every string, `data-testid` on every interactive element.

### Components

Under `modules/WorkoutPlans/resources/js/components/`:

- **`ExerciseLibrary.vue`** — search box, type and muscle filters, exercise cards. Prop `variant: 'sidebar' | 'grid'`. Used both as the builder's left column and as the body of the standalone library page. One search implementation, one filter implementation.
- `ExerciseCard.vue` — thumbnail, name, muscle badges, `+` button, actions menu (edit/delete for custom only)
- `VideoPreviewModal.vue` — `<iframe>` to the allowlisted provider
- `ExerciseFormModal.vue` — create/edit a custom exercise
- `DayTabs.vue` — day tabs with add/remove/rename
- `PlanExerciseCard.vue` — inline-editable `sets`, `reps_target`, `weight_kg`, `rir_target`, `rest_seconds`, `notes`; `×` to remove
- `PlanSettingsPanel.vue` — name, description, template toggle, client multi-select, Save / Save and Assign
- `BuilderToolbar.vue` — back link, duplicate, save badge

### Builder layout

Three columns: library at 280px fixed, days flexible, settings at 240px fixed. Below `lg`, the side columns collapse into sheets triggered from the toolbar.

Dark mode variants on every component, per project convention.

### State

A composable, `usePlanBuilder.ts`. Pinia is not in this project and is not being added.

```ts
const plan = reactive<PlanTree>(initial)      // source of truth
const planId = ref<number | null>(props.plan?.id ?? null)
const isDirty = ref(false)
const lastSavedAt = ref<Date | null>(null)
const saveState = ref<'idle' | 'saving' | 'error'>('idle')
```

### Drag and drop

New dependencies: `sortablejs` and `@vueuse/integrations` (`@vueuse/core` is already installed).

Two gestures: dragging from the library into a day (cross-list, clone) and reordering within a day (same-list, move). Both are covered by `useSortable` with `group: { name: 'exercises', pull: 'clone' }`.

SortableJS mutates the DOM directly. The `onEnd` handler reorders **the array**; Vue re-renders from that. On a cross-list clone, the cloned node must be removed from the DOM before Vue reconciles, or the element appears twice. This is the well-known failure mode — it is isolated inside a thin `useSortable` wrapper and pinned by an E2E test.

The `+` button on every exercise card does the same thing as a drag: appends to the active day. It is the keyboard path, the screen-reader path, and the mobile path. Reordering has `↑`/`↓` buttons for the same reason. Drag is an addition to a complete interaction, not a replacement for one.

### Auto-save

`watch(plan, ...)` sets `isDirty`. A 30-second debounce then saves. On top of that: an immediate save on `blur` of any inline field, and a `navigator.sendBeacon` save on `beforeunload` when dirty.

One save in flight at a time. If a save is triggered while another is running, it waits for the first to settle rather than queueing — the payload is the whole tree, so the later save subsumes the earlier one.

## Error handling

| Case | Behavior |
| --- | --- |
| Save fails | Badge turns red, "Errore, riprovo". Retry with backoff at 2s, 8s, 30s. After the third failure: badge "Non salvato" and a persistent banner with a Retry button. Local `plan` state is never cleared. |
| `PUT` returns 404 (plan deleted elsewhere) | Banner: "Questo piano non esiste più", with a "Salva come nuovo" button that issues a `POST`. |
| Two tabs editing the same plan | Last write wins, silently. No locking, no ETag. This matches how Filament behaves today. Stated here so it is a choice, not an oversight. |
| Custom exercise deleted while used in a plan | Blocked by `restrictOnDelete`; the delete responds 422 with the number of plans using it. |
| Plan with no days, or a day with no exercises | Allowed. A plan gets built in pieces. |
| "Salva e Assegna" with no clients selected | Button disabled. |

## Testing

**Unit**

- `WorkoutPlanWriter`: upserts preserve `workout_exercises.id`; removed days and exercises are deleted; `order_index` follows array position; assignments sync only when requested.
- `AllowedVideoHost`: accepts allowlisted https hosts, rejects everything else including `http://youtube.com` and lookalike domains.

**Feature**

- Policies: coach A cannot read, update, delete, or duplicate coach B's plan; coach A cannot reference coach B's custom exercise in a plan payload; a coach cannot edit a global exercise; an admin can still edit a global exercise through the Filament resource.
- Validation bounds on every numeric field.
- `duplicate` inherits `is_template` from the source (regression test for the current bug).
- Deleting a custom exercise in use returns 422 and leaves the plan intact.
- The migration backfills `plan_assignments` from `workout_plans.client_id` before dropping the column, and backfills `reps_target` from `reps`.

**E2E** (`@WorkoutPlans` project)

- Dragging from the library into a day creates one card and leaves no ghost node.
- Reordering within a day persists across a reload.
- Auto-save flips the badge from "Bozza" to "Salvato".
- Editing an inline field and blurring saves immediately.
- The `+` button and the `↑`/`↓` buttons produce the same result as the equivalent drag.

`data-testid` names: `exercise-card-${id}`, `add-exercise-${id}`, `day-tab-${n}`, `add-day-btn`, `plan-exercise-${id}`, `remove-exercise-${id}`, `save-badge`, `save-plan-btn`, `save-assign-btn`, `library-search`, `library-filter-type`, `library-filter-muscle`.

**Commands**

```bash
php -d memory_limit=2048M artisan test --testsuite=Modules --filter='^Modules\WorkoutPlans\Tests'
npx playwright test --project="@WorkoutPlans*"
```

## Follow-up specs

1. **AI load progression** — fills in `CalculateAiProgressionJob`, which is currently a stub that only logs. Needs a suggestion service, confidence scoring, reasoning text, and caching. Note that `personal_records` is a current-max store (unique on `client_id, exercise_id`, updated in place), not a history — the "last 5 sessions" table must be built from `session_sets` joined to `workout_sessions`, and the 🏆 row is the one matching the `PersonalRecord` weight. Depends on clients having logged at least two sessions, so it is weeks away from being useful.
2. **Native video upload** — presigned S3, size limits, ffmpeg thumbnails.
3. **Multi-week periodization** — gives `week_number` and `duration_weeks` real meaning.

## Decisions log

| Decision | Chosen | Rejected |
| --- | --- | --- |
| Web transport | Inertia web routes + shared service | Session guard on v1 API; JWT in page props |
| Save granularity | Whole-tree PUT with upsert-by-id | Granular endpoints; explicit-save-only |
| Plan creation | On first meaningful save | On page open; `status` column |
| Draft state | Client-side `isDirty` | Database column |
| Assignment | `plan_assignments` only; drop `client_id` | Keep both |
| Legacy columns | Drop `order` and `reps`, backfill first | Leave them |
| Video | URL only, host allowlist | Presigned S3; spatie/media-library |
| Drag and drop | `sortablejs` + `@vueuse/integrations`, with `+` button fallback | `vuedraggable`; no drag at all |
| Exercise delete FK | `restrictOnDelete`, 422 when in use | `cascadeOnDelete` (silent plan corruption) |
| Concurrent edits | Last write wins | Locking or ETag |
| Weeks | Ignored in v1 | Week tabs in the builder |
| Scope | Library + builder in one spec | Bundling AI progression |
