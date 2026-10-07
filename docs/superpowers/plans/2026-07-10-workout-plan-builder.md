# Workout Plan Builder + Exercise Library Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the coach-facing web UI for the `WorkoutPlans` module — a three-column drag-and-drop plan builder and a standalone exercise library — on top of the existing models and API.

**Architecture:** Two HTTP surfaces on one domain. `/api/v1/*` stays JWT-authenticated for the mobile app; a new `/coach/*` surface uses session `auth` + `role:coach` with Inertia. Plan persistence is extracted into `WorkoutPlanWriter`, called by both. The builder sends the whole plan tree on every save and the service upserts by id inside a transaction.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia 3, Vue 3 Composition API, TypeScript 5.8, Tailwind 4, PHPUnit 12, Playwright, `sortablejs` (new).

**Spec:** `docs/superpowers/specs/2026-07-10-workout-plan-builder-design.md`

## Global Constraints

- PHP: curly braces on all control structures; constructor property promotion; explicit return types and parameter type hints on every method; PHPDoc array shapes.
- Every Vue component ships both light and dark variants (`bg-white dark:bg-gray-900`, `text-gray-900 dark:text-white`, `text-gray-600 dark:text-gray-400`, `border-gray-200 dark:border-gray-800`).
- Every interactive element gets a `data-testid`. Item-specific ones use `{action}-${item.id}`. E2E tests never select by translated text, label, or role.
- All user-facing strings go through `$t()`.
- Coach pages wrap in `CoachLayout` (`@/layouts/CoachLayout.vue`), which takes `title` and `breadcrumbs`.
- Tables are hand-rolled semantic `<table>` inside a `Card`, following `modules/Users/resources/js/pages/Coach/Clients/Index.vue`. There is no shared DataTable component.
- UI primitives come from `@/components/ui/*`. Icons from `~icons/heroicons/*`.
- `spatie/laravel-data` is not a direct dependency (`composer.json`); it reaches `vendor/` only transitively, through `miocoach/billing`. Do not add it to `composer.json` and do not build on it — a module's DTOs must not depend on another module's transitive dependency. DTOs here are plain readonly PHP classes with `#[TypeScript]`, transformed by the configured `DtoTransformer`.
- Pinia is NOT installed and must not be added. Builder state lives in a composable.
- Run `vendor/bin/pint --dirty --format agent` before every PHP commit.
- Tests: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='Modules\\WorkoutPlans\\Tests'`. E2E: `npx playwright test --project="@WorkoutPlans*"`.
- The test suite runs on SQLite in-memory; production runs MySQL 8. No raw SQL that only one of them understands (`NOW()` does not exist in SQLite).
- Never modify the athlete-facing mobile API contract beyond the two fixes in Tasks 2 and 4.

## File Structure

**Create**

| Path | Responsibility |
| --- | --- |
| `modules/WorkoutPlans/database/migrations/2026_07_10_000001_cleanup_workout_plans_schema.php` | Backfill + drop dead columns; tighten exercise FK |
| `modules/WorkoutPlans/app/Policies/WorkoutPlanPolicy.php` | Plan ownership rules |
| `modules/WorkoutPlans/app/Policies/ExercisePolicy.php` | Custom-vs-global exercise rules |
| `app/Rules/AllowedVideoHost.php` | Validates video URLs against an allowlist |
| `modules/WorkoutPlans/app/Services/VideoThumbnailResolver.php` | oEmbed thumbnail lookup |
| `modules/WorkoutPlans/app/Data/PlanTreeData.php` | Save payload root |
| `modules/WorkoutPlans/app/Data/PlanDayData.php` | One day |
| `modules/WorkoutPlans/app/Data/PlanExerciseData.php` | One exercise in a day |
| `modules/WorkoutPlans/app/Services/WorkoutPlanWriter.php` | Transactional upsert of the plan tree |
| `modules/WorkoutPlans/app/Http/Controllers/Coach/PlanBuilderController.php` | Web plan CRUD |
| `modules/WorkoutPlans/app/Http/Controllers/Coach/ExerciseController.php` | Web exercise CRUD |
| `modules/WorkoutPlans/app/Http/Requests/StoreCoachPlanRequest.php` | Plan tree validation (create) |
| `modules/WorkoutPlans/app/Http/Requests/UpdateCoachPlanRequest.php` | Plan tree validation (update) |
| `modules/WorkoutPlans/app/Http/Requests/StoreCoachExerciseRequest.php` | Custom exercise validation |
| `modules/WorkoutPlans/app/Http/Requests/UpdateCoachExerciseRequest.php` | Custom exercise validation |
| `modules/WorkoutPlans/database/factories/WorkoutExerciseFactory.php` | Needed by writer tests |
| `modules/WorkoutPlans/resources/js/composables/useSortableList.ts` | SortableJS ↔ Vue array bridge |
| `modules/WorkoutPlans/resources/js/composables/usePlanBuilder.ts` | Builder state + auto-save |
| `modules/WorkoutPlans/resources/js/components/ExerciseLibrary.vue` | Search + filters + card list, two variants |
| `modules/WorkoutPlans/resources/js/components/ExerciseCard.vue` | One exercise card |
| `modules/WorkoutPlans/resources/js/components/VideoPreviewModal.vue` | Provider iframe |
| `modules/WorkoutPlans/resources/js/components/ExerciseFormModal.vue` | Create/edit custom exercise |
| `modules/WorkoutPlans/resources/js/components/DayTabs.vue` | Day tabs |
| `modules/WorkoutPlans/resources/js/components/PlanExerciseCard.vue` | Inline-editable exercise in a day |
| `modules/WorkoutPlans/resources/js/components/PlanSettingsPanel.vue` | Right column |
| `modules/WorkoutPlans/resources/js/components/BuilderToolbar.vue` | Header toolbar + save badge |
| `modules/WorkoutPlans/resources/js/pages/Coach/Plans/Index.vue` | Plan list + stat cards |
| `modules/WorkoutPlans/resources/js/pages/Coach/Plans/Builder.vue` | Three-column builder |
| `modules/WorkoutPlans/resources/js/pages/Coach/Exercises/Index.vue` | Standalone library |
| `modules/WorkoutPlans/tests/Unit/WorkoutPlanWriterTest.php` | |
| `modules/WorkoutPlans/tests/Unit/AllowedVideoHostTest.php` | |
| `modules/WorkoutPlans/tests/Feature/CleanupSchemaMigrationTest.php` | |
| `modules/WorkoutPlans/tests/Feature/PolicyTest.php` | |
| `modules/WorkoutPlans/tests/Feature/Coach/PlanBuilderControllerTest.php` | |
| `modules/WorkoutPlans/tests/Feature/Coach/ExerciseControllerTest.php` | |
| `modules/WorkoutPlans/tests/e2e/builder.spec.ts` | |
| `modules/WorkoutPlans/tests/e2e/exercise-library.spec.ts` | |

**Modify**

| Path | Change |
| --- | --- |
| `modules/WorkoutPlans/app/Models/WorkoutExercise.php` | Drop `order`/`reps` from `$fillable`, `$casts`, PHPDoc |
| `modules/WorkoutPlans/app/Models/WorkoutPlan.php` | Drop `client_id` and `client()` |
| `modules/WorkoutPlans/app/Http/Controllers/Api/V1/WorkoutPlanController.php` | Use policies + writer; fix `duplicate` |
| `modules/WorkoutPlans/app/Http/Controllers/Api/V1/ExerciseController.php` | Use policy; block delete when in use |
| `modules/WorkoutPlans/app/Http/Requests/StoreExerciseRequest.php` | Add `AllowedVideoHost` |
| `modules/WorkoutPlans/app/Http/Requests/UpdateExerciseRequest.php` | Add `AllowedVideoHost` |
| `modules/WorkoutPlans/app/Providers/WorkoutPlansServiceProvider.php` | Register policies |
| `modules/WorkoutPlans/config/config.php` | Add `video_hosts` |
| `modules/WorkoutPlans/routes/web.php` | Replace the single route with the `/coach` group |
| `modules/WorkoutPlans/routes/navigation.php` | Point at `coach.plans.index` |
| `modules/WorkoutPlans/app/Http/Controllers/WorkoutPlansController.php` | Delete (superseded) |
| `modules/WorkoutPlans/resources/js/pages/Index.vue` | Delete (moved to `Coach/Plans/Index.vue`) |
| `package.json` | Add `sortablejs` |

---

### Task 1: Schema cleanup migration

Drops three dead columns and tightens one foreign key. Everything else in this plan depends on the final shape of the schema, so it goes first.

**Files:**
- Create: `modules/WorkoutPlans/database/migrations/2026_07_10_000001_cleanup_workout_plans_schema.php`
- Create: `modules/WorkoutPlans/database/factories/WorkoutExerciseFactory.php`
- Modify: `modules/WorkoutPlans/app/Models/WorkoutExercise.php`
- Modify: `modules/WorkoutPlans/app/Models/WorkoutPlan.php`
- Test: `modules/WorkoutPlans/tests/Feature/CleanupSchemaMigrationTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `workout_exercises` without `order`/`reps`; `workout_plans` without `client_id`; `workout_exercises.exercise_id` FK is `restrictOnDelete`. `WorkoutExerciseFactory` with a `definition()` returning `workout_day_id`, `exercise_id`, `sets`, `reps_target`, `weight_kg`, `rir_target`, `rest_seconds`, `notes`, `order_index`.

- [ ] **Step 1: Write the failing test**

Create `modules/WorkoutPlans/tests/Feature/CleanupSchemaMigrationTest.php`:

```php
<?php

namespace Modules\WorkoutPlans\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\WorkoutPlans\Models\Exercise;
use Modules\WorkoutPlans\Models\WorkoutDay;
use Modules\WorkoutPlans\Models\WorkoutExercise;
use Modules\WorkoutPlans\Models\WorkoutPlan;
use Tests\TestCase;

class CleanupSchemaMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dead_columns_are_gone(): void
    {
        $this->assertFalse(Schema::hasColumn('workout_exercises', 'order'));
        $this->assertFalse(Schema::hasColumn('workout_exercises', 'reps'));
        $this->assertFalse(Schema::hasColumn('workout_plans', 'client_id'));
    }

    public function test_surviving_columns_are_untouched(): void
    {
        $this->assertTrue(Schema::hasColumn('workout_exercises', 'order_index'));
        $this->assertTrue(Schema::hasColumn('workout_exercises', 'reps_target'));
        $this->assertTrue(Schema::hasColumn('workout_exercises', 'week_number'));
        $this->assertTrue(Schema::hasColumn('workout_plans', 'duration_weeks'));
    }

    public function test_deleting_an_exercise_used_by_a_plan_is_blocked_at_the_database(): void
    {
        $plan = WorkoutPlan::factory()->create();
        $day = WorkoutDay::factory()->create(['workout_plan_id' => $plan->id]);
        $exercise = Exercise::factory()->global()->create();

        WorkoutExercise::factory()->create([
            'workout_plan_id' => $plan->id,
            'workout_day_id' => $day->id,
            'exercise_id' => $exercise->id,
        ]);

        $this->expectException(QueryException::class);

        // forceDelete bypasses SoftDeletes and hits the FK constraint.
        $exercise->forceDelete();
    }
}
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `php -d memory_limit=2048M artisan test --compact --filter=CleanupSchemaMigrationTest`

Expected: FAIL. `test_dead_columns_are_gone` fails because `order` still exists; `test_deleting_an_exercise...` fails because `WorkoutExerciseFactory` does not exist ("Call to undefined method ... factory()").

- [ ] **Step 3: Add the missing factory**

`WorkoutExercise` has no factory today. Create `modules/WorkoutPlans/database/factories/WorkoutExerciseFactory.php`:

```php
<?php

namespace Modules\WorkoutPlans\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\WorkoutPlans\Models\Exercise;
use Modules\WorkoutPlans\Models\WorkoutExercise;

/**
 * @extends Factory<WorkoutExercise>
 */
class WorkoutExerciseFactory extends Factory
{
    protected $model = WorkoutExercise::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'workout_plan_id' => null,
            'workout_day_id' => null,
            'exercise_id' => Exercise::factory(),
            'sets' => fake()->numberBetween(3, 5),
            'reps_target' => fake()->numberBetween(6, 12),
            'weight_kg' => fake()->randomFloat(2, 20, 120),
            'rir_target' => fake()->numberBetween(0, 4),
            'rest_seconds' => fake()->randomElement([60, 90, 120]),
            'notes' => null,
            'order_index' => 0,
        ];
    }
}
```

Wire it into the model. Add to `modules/WorkoutPlans/app/Models/WorkoutExercise.php`, inside the class, above `$table`:

```php
    /** @use HasFactory<WorkoutExerciseFactory> */
    use HasFactory;

    protected static function newFactory(): WorkoutExerciseFactory
    {
        return WorkoutExerciseFactory::new();
    }
```

Add the imports `Illuminate\Database\Eloquent\Factories\HasFactory` and `Modules\WorkoutPlans\Database\Factories\WorkoutExerciseFactory`.

- [ ] **Step 4: Write the migration**

Create `modules/WorkoutPlans/database/migrations/2026_07_10_000001_cleanup_workout_plans_schema.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rescue assignments that only ever existed as workout_plans.client_id.
        // DB::table (not Eloquent) so soft-deleted plans are included too.
        DB::table('workout_plans')
            ->whereNotNull('client_id')
            ->orderBy('id')
            ->chunkById(100, function ($plans): void {
                foreach ($plans as $plan) {
                    DB::table('plan_assignments')->updateOrInsert(
                        ['workout_plan_id' => $plan->id, 'client_id' => $plan->client_id],
                        [
                            'assigned_at' => $plan->created_at ?? now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                }
            });

        // Legacy `reps` is superseded by `reps_target`. Preserve any value first.
        DB::table('workout_exercises')
            ->whereNull('reps_target')
            ->whereNotNull('reps')
            ->update(['reps_target' => DB::raw('reps')]);

        Schema::table('workout_exercises', function (Blueprint $table): void {
            $table->dropColumn(['order', 'reps']);
        });

        Schema::table('workout_plans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('client_id');
        });

        // cascadeOnDelete silently shortened every plan using a deleted exercise.
        Schema::table('workout_exercises', function (Blueprint $table): void {
            $table->dropForeign(['exercise_id']);
            $table->foreign('exercise_id')->references('id')->on('exercises')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workout_exercises', function (Blueprint $table): void {
            $table->dropForeign(['exercise_id']);
            $table->foreign('exercise_id')->references('id')->on('exercises')->cascadeOnDelete();
            $table->unsignedSmallInteger('order')->default(0);
            $table->unsignedSmallInteger('reps')->nullable();
        });

        Schema::table('workout_plans', function (Blueprint $table): void {
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
        });
    }
};
```

Note: `down()` restores the columns but not their data. That is accepted in the spec — `client_id` had no readers and `reps` was backfilled into `reps_target` before being dropped.

- [ ] **Step 5: Clean the models**

In `modules/WorkoutPlans/app/Models/WorkoutExercise.php`: delete `'order'` and `'reps'` from `$fillable`, delete `'order' => 'integer'` and `'reps' => 'integer'` from `casts()`, and delete the `@property int $order` and `@property int|null $reps` PHPDoc lines.

In `modules/WorkoutPlans/app/Models/WorkoutPlan.php`: delete `'client_id'` from `$fillable`, delete the `@property int|null $client_id` line, delete the entire `client()` method, and remove the now-unused `use App\Modules\Users\Models\Client;` import.

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php -d memory_limit=2048M artisan test --compact --filter=CleanupSchemaMigrationTest`

Expected: PASS, 3 tests.

Then run the whole module suite to catch anything that referenced the dropped columns:

Run: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='Modules\\WorkoutPlans\\Tests'`

Expected: PASS. If `WorkoutPlanFactory` or a seeder sets `client_id`, remove it there.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add modules/WorkoutPlans/database/migrations/2026_07_10_000001_cleanup_workout_plans_schema.php \
        modules/WorkoutPlans/database/factories/WorkoutExerciseFactory.php \
        modules/WorkoutPlans/app/Models/WorkoutExercise.php \
        modules/WorkoutPlans/app/Models/WorkoutPlan.php \
        modules/WorkoutPlans/tests/Feature/CleanupSchemaMigrationTest.php
git commit -m "refactor(workoutplans): drop dead columns and restrict exercise deletion"
```

---

### Task 2: Policies

Replaces the inline ownership checks scattered through the API controllers with two policies shared by both HTTP surfaces.

**Files:**
- Create: `modules/WorkoutPlans/app/Policies/WorkoutPlanPolicy.php`
- Create: `modules/WorkoutPlans/app/Policies/ExercisePolicy.php`
- Modify: `modules/WorkoutPlans/app/Providers/WorkoutPlansServiceProvider.php`
- Modify: `modules/WorkoutPlans/app/Http/Controllers/Api/V1/WorkoutPlanController.php`
- Modify: `modules/WorkoutPlans/app/Http/Controllers/Api/V1/ExerciseController.php`
- Test: `modules/WorkoutPlans/tests/Feature/PolicyTest.php`

**Interfaces:**
- Consumes: Task 1's schema.
- Produces: `WorkoutPlanPolicy` with `view/update/delete/duplicate(User $user, WorkoutPlan $plan): bool`; `ExercisePolicy` with `update/delete(User $user, Exercise $exercise): bool`. Both grant everything to `admin` via `before()`. Later tasks call `$this->authorize('update', $plan)`.

- [ ] **Step 1: Write the failing test**

Create `modules/WorkoutPlans/tests/Feature/PolicyTest.php`:

```php
<?php

namespace Modules\WorkoutPlans\Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\WorkoutPlans\Models\Exercise;
use Modules\WorkoutPlans\Models\WorkoutPlan;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    private function makeCoach(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole(Role::COACH);
        $coach = Coach::factory()->create(['user_id' => $user->id]);

        return [$user, $coach];
    }

    public function test_coach_can_update_own_plan(): void
    {
        [$user, $coach] = $this->makeCoach();
        $plan = WorkoutPlan::factory()->create(['coach_id' => $coach->id]);

        $this->assertTrue($user->can('update', $plan));
    }

    public function test_coach_cannot_update_another_coachs_plan(): void
    {
        [$userA] = $this->makeCoach();
        [, $coachB] = $this->makeCoach();
        $plan = WorkoutPlan::factory()->create(['coach_id' => $coachB->id]);

        $this->assertFalse($userA->can('update', $plan));
    }

    public function test_coach_can_update_own_custom_exercise(): void
    {
        [$user, $coach] = $this->makeCoach();
        $exercise = Exercise::factory()->create(['coach_id' => $coach->id]);

        $this->assertTrue($user->can('update', $exercise));
    }

    public function test_coach_cannot_update_a_global_exercise(): void
    {
        [$user] = $this->makeCoach();
        $exercise = Exercise::factory()->global()->create();

        $this->assertFalse($user->can('update', $exercise));
    }

    public function test_admin_can_update_a_global_exercise(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole(Role::ADMIN);
        $exercise = Exercise::factory()->global()->create();

        $this->assertTrue($admin->can('update', $exercise));
    }

    public function test_admin_can_update_any_plan(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole(Role::ADMIN);
        [, $coach] = $this->makeCoach();
        $plan = WorkoutPlan::factory()->create(['coach_id' => $coach->id]);

        $this->assertTrue($admin->can('update', $plan));
    }
}
```

The last two tests are the important ones. Filament v5 applies registered policies to its resources. Without the `admin` bypass, introducing `ExercisePolicy` would lock admins out of the twelve seeded global exercises they manage through `ExerciseResource`.

- [ ] **Step 2: Run it to make sure it fails**

Run: `php -d memory_limit=2048M artisan test --compact --filter=PolicyTest`

Expected: FAIL. Without a policy, `$user->can('update', $plan)` returns `false` for every case, so the four "can" tests fail.

- [ ] **Step 3: Write the policies**

Create `modules/WorkoutPlans/app/Policies/WorkoutPlanPolicy.php`:

```php
<?php

namespace Modules\WorkoutPlans\Policies;

use App\Enums\Role;
use App\Models\User;
use Modules\WorkoutPlans\Models\PlanAssignment;
use Modules\WorkoutPlans\Models\WorkoutPlan;

class WorkoutPlanPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole(Role::ADMIN) ? true : null;
    }

    public function view(User $user, WorkoutPlan $plan): bool
    {
        if ($this->owns($user, $plan)) {
            return true;
        }

        return $user->client !== null && PlanAssignment::query()
            ->where('workout_plan_id', $plan->id)
            ->where('client_id', $user->client->id)
            ->exists();
    }

    public function update(User $user, WorkoutPlan $plan): bool
    {
        return $this->owns($user, $plan);
    }

    public function delete(User $user, WorkoutPlan $plan): bool
    {
        return $this->owns($user, $plan);
    }

    public function duplicate(User $user, WorkoutPlan $plan): bool
    {
        return $this->owns($user, $plan);
    }

    private function owns(User $user, WorkoutPlan $plan): bool
    {
        return $user->coach !== null && $plan->coach_id === $user->coach->id;
    }
}
```

Create `modules/WorkoutPlans/app/Policies/ExercisePolicy.php`:

```php
<?php

namespace Modules\WorkoutPlans\Policies;

use App\Enums\Role;
use App\Models\User;
use Modules\WorkoutPlans\Models\Exercise;

class ExercisePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole(Role::ADMIN) ? true : null;
    }

    public function update(User $user, Exercise $exercise): bool
    {
        return $this->ownsCustom($user, $exercise);
    }

    public function delete(User $user, Exercise $exercise): bool
    {
        return $this->ownsCustom($user, $exercise);
    }

    /** Global exercises (coach_id === null) are read-only for coaches. */
    private function ownsCustom(User $user, Exercise $exercise): bool
    {
        return $exercise->coach_id !== null
            && $user->coach !== null
            && $exercise->coach_id === $user->coach->id;
    }
}
```

- [ ] **Step 4: Register them**

Laravel's policy guesser maps `App\Models\X` to `App\Policies\XPolicy`; it does not find `Modules\WorkoutPlans\Policies\*`. Register explicitly in `modules/WorkoutPlans/app/Providers/WorkoutPlansServiceProvider.php`, inside `boot()`:

```php
        Gate::policy(WorkoutPlan::class, WorkoutPlanPolicy::class);
        Gate::policy(Exercise::class, ExercisePolicy::class);
```

Add the imports: `Illuminate\Support\Facades\Gate`, `Modules\WorkoutPlans\Models\Exercise`, `Modules\WorkoutPlans\Models\WorkoutPlan`, `Modules\WorkoutPlans\Policies\ExercisePolicy`, `Modules\WorkoutPlans\Policies\WorkoutPlanPolicy`.

If `boot()` calls `parent::boot()`, keep that call first.

- [ ] **Step 5: Run the test to verify it passes**

Run: `php -d memory_limit=2048M artisan test --compact --filter=PolicyTest`

Expected: PASS, 6 tests.

- [ ] **Step 6: Swap the inline checks for policy calls**

In `modules/WorkoutPlans/app/Http/Controllers/Api/V1/WorkoutPlanController.php`, replace each of these blocks:

```php
        $isOwnerCoach = $user->coach && $workoutPlan->coach_id === $user->coach->id;
        $isAssignedClient = $user->client && PlanAssignment::where('workout_plan_id', $workoutPlan->id)
            ->where('client_id', $user->client->id)
            ->exists();

        if (! $isOwnerCoach && ! $isAssignedClient) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }
```

in `show()` with:

```php
        if ($user->cannot('view', $workoutPlan)) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }
```

and in `update()`, `destroy()`, `duplicate()`, `assign()`, replace:

```php
        if (! $coach || $workoutPlan->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }
```

with the matching ability (`update`, `delete`, `duplicate`, `update` respectively):

```php
        if ($request->user()->cannot('update', $workoutPlan)) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }
```

Keep the `$coach = $request->user()->coach;` line where the method body still uses `$coach`. Drop the now-unused `PlanAssignment` import from `show()`'s needs only if no other method uses it — `assign()` still does, so keep it.

In `modules/WorkoutPlans/app/Http/Controllers/Api/V1/ExerciseController.php`, in `update()` and `destroy()`, replace:

```php
        if (! $coach || $exercise->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }
```

with:

```php
        if ($request->user()->cannot('update', $exercise)) {   // 'delete' in destroy()
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }
```

and delete the now-unused `$coach = $request->user()->coach;` line from both.

- [ ] **Step 7: Run the API tests to verify nothing regressed**

Run: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='Modules\\WorkoutPlans\\Tests'`

Expected: PASS. The existing `WorkoutPlanControllerTest` and `ExerciseControllerTest` must still be green — the policies encode exactly the rules the inline checks did.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add modules/WorkoutPlans/app/Policies modules/WorkoutPlans/app/Providers/WorkoutPlansServiceProvider.php \
        modules/WorkoutPlans/app/Http/Controllers/Api/V1 modules/WorkoutPlans/tests/Feature/PolicyTest.php
git commit -m "refactor(workoutplans): extract ownership rules into policies"
```

---

### Task 3: Video host allowlist + thumbnail resolver

`videoUrl` currently accepts any URL that passes Laravel's `url` rule. That value ends up rendered back to other users. Constrain it to a handful of known video providers, and derive the thumbnail from the provider's oEmbed endpoint.

**Files:**
- Create: `app/Rules/AllowedVideoHost.php`
- Create: `modules/WorkoutPlans/app/Services/VideoThumbnailResolver.php`
- Modify: `modules/WorkoutPlans/config/config.php`
- Modify: `modules/WorkoutPlans/app/Http/Requests/StoreExerciseRequest.php`
- Modify: `modules/WorkoutPlans/app/Http/Requests/UpdateExerciseRequest.php`
- Modify: `modules/WorkoutPlans/app/Http/Controllers/Api/V1/ExerciseController.php`
- Test: `modules/WorkoutPlans/tests/Unit/AllowedVideoHostTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: `App\Rules\AllowedVideoHost` (a `ValidationRule`); `VideoThumbnailResolver::resolve(?string $url): ?string`. Tasks 9 and 14 reuse both.

- [ ] **Step 1: Write the failing test**

Create `modules/WorkoutPlans/tests/Unit/AllowedVideoHostTest.php`:

```php
<?php

namespace Modules\WorkoutPlans\Tests\Unit;

use App\Rules\AllowedVideoHost;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AllowedVideoHostTest extends TestCase
{
    /** @param array<int, string> $hosts */
    private function passes(string $url): bool
    {
        return Validator::make(
            ['videoUrl' => $url],
            ['videoUrl' => [new AllowedVideoHost]],
        )->passes();
    }

    public function test_it_accepts_allowlisted_https_hosts(): void
    {
        $this->assertTrue($this->passes('https://www.youtube.com/watch?v=abc123'));
        $this->assertTrue($this->passes('https://youtu.be/abc123'));
        $this->assertTrue($this->passes('https://vimeo.com/123456'));
        $this->assertTrue($this->passes('https://player.vimeo.com/video/123456'));
    }

    public function test_it_rejects_plain_http(): void
    {
        $this->assertFalse($this->passes('http://youtube.com/watch?v=abc123'));
    }

    public function test_it_rejects_unknown_hosts(): void
    {
        $this->assertFalse($this->passes('https://evil.example.com/video.mp4'));
    }

    public function test_it_rejects_lookalike_hosts(): void
    {
        $this->assertFalse($this->passes('https://youtube.com.evil.example.com/x'));
        $this->assertFalse($this->passes('https://notyoutube.com/watch?v=abc'));
        $this->assertFalse($this->passes('https://evil.com/?u=https://youtube.com/x'));
    }

    public function test_it_rejects_garbage(): void
    {
        $this->assertFalse($this->passes('not a url'));
        $this->assertFalse($this->passes('javascript:alert(1)'));
    }
}
```

The lookalike cases are the point of the rule. A naive `str_contains($url, 'youtube.com')` passes all three.

- [ ] **Step 2: Run it to make sure it fails**

Run: `php -d memory_limit=2048M artisan test --compact --filter=AllowedVideoHostTest`

Expected: FAIL with `Class "App\Rules\AllowedVideoHost" not found`.

- [ ] **Step 3: Add the config**

Replace `modules/WorkoutPlans/config/config.php` with:

```php
<?php

return [
    'name' => 'WorkoutPlans',

    /*
     | Hosts accepted in Exercise::$video_url. Exact host match, https only.
     | The preview renders as an <iframe> to one of these, never a <video>
     | element pointed at a user-supplied URL.
     */
    'video_hosts' => [
        'youtube.com',
        'www.youtube.com',
        'youtu.be',
        'vimeo.com',
        'player.vimeo.com',
    ],
];
```

- [ ] **Step 4: Write the rule**

Create `app/Rules/AllowedVideoHost.php`:

```php
<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AllowedVideoHost implements ValidationRule
{
    /** @param  Closure(string): void  $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('validation.custom.video_url.allowed_host')->translate();

            return;
        }

        $parts = parse_url($value);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            $fail('validation.custom.video_url.allowed_host')->translate();

            return;
        }

        /** @var array<int, string> $allowed */
        $allowed = config('workoutplans.video_hosts', []);

        if ($parts['scheme'] !== 'https' || ! in_array(strtolower($parts['host']), $allowed, true)) {
            $fail('validation.custom.video_url.allowed_host')->translate();
        }
    }
}
```

Exact host match against the allowlist, so `youtube.com.evil.example.com` and `notyoutube.com` both fail. `parse_url` on `javascript:alert(1)` yields no host, so that fails too.

Add the message to `lang/it/validation.php`, inside the `custom` array:

```php
        'video_url' => [
            'allowed_host' => 'Il link video deve essere un URL https di YouTube o Vimeo.',
        ],
```

And the same key in `lang/en/validation.php`:

```php
        'video_url' => [
            'allowed_host' => 'The video link must be an https URL from YouTube or Vimeo.',
        ],
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php -d memory_limit=2048M artisan test --compact --filter=AllowedVideoHostTest`

Expected: PASS, 5 tests.

- [ ] **Step 6: Apply the rule to the existing API requests**

In `modules/WorkoutPlans/app/Http/Requests/StoreExerciseRequest.php`, change the `videoUrl` line to:

```php
            'videoUrl' => ['nullable', 'url', 'max:500', new AllowedVideoHost],
```

In `modules/WorkoutPlans/app/Http/Requests/UpdateExerciseRequest.php`:

```php
            'videoUrl' => ['sometimes', 'nullable', 'url', 'max:500', new AllowedVideoHost],
```

Add `use App\Rules\AllowedVideoHost;` to both.

- [ ] **Step 7: Write the thumbnail resolver**

Create `modules/WorkoutPlans/app/Services/VideoThumbnailResolver.php`:

```php
<?php

namespace Modules\WorkoutPlans\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class VideoThumbnailResolver
{
    /**
     * Ask the provider's oEmbed endpoint for a thumbnail.
     * A missing thumbnail never blocks a save, so every failure returns null.
     */
    public function resolve(?string $videoUrl): ?string
    {
        if ($videoUrl === null || $videoUrl === '') {
            return null;
        }

        $host = strtolower((string) parse_url($videoUrl, PHP_URL_HOST));

        $endpoint = match (true) {
            str_contains($host, 'youtu') => 'https://www.youtube.com/oembed',
            str_contains($host, 'vimeo') => 'https://vimeo.com/api/oembed.json',
            default => null,
        };

        if ($endpoint === null) {
            return null;
        }

        try {
            $response = Http::timeout(3)->get($endpoint, ['url' => $videoUrl, 'format' => 'json']);

            if ($response->failed()) {
                return null;
            }

            $thumbnail = $response->json('thumbnail_url');

            return is_string($thumbnail) ? $thumbnail : null;
        } catch (Throwable) {
            return null;
        }
    }
}
```

The host has already passed `AllowedVideoHost` by the time this runs, so the loose `str_contains` here is matching an already-validated value, not deciding trust.

- [ ] **Step 8: Use it in the API store/update**

In `modules/WorkoutPlans/app/Http/Controllers/Api/V1/ExerciseController.php`, inject the resolver and set the thumbnail. Change the class to take a constructor:

```php
    public function __construct(private VideoThumbnailResolver $thumbnails) {}
```

In `store()`, add to the `Exercise::create([...])` array:

```php
            'thumbnail_url' => $this->thumbnails->resolve($request->validated('videoUrl')),
```

In `update()`, add to the `array_filter([...])` array:

```php
            'thumbnail_url' => $request->has('videoUrl')
                ? $this->thumbnails->resolve($request->validated('videoUrl'))
                : null,
```

Add `use Modules\WorkoutPlans\Services\VideoThumbnailResolver;`.

- [ ] **Step 9: Verify the existing API tests still pass**

`ExerciseControllerTest` may post a `videoUrl` that the new rule rejects. Run:

Run: `php -d memory_limit=2048M artisan test --compact --filter=ExerciseControllerTest`

Expected: PASS. If a test posts `https://example.com/video.mp4`, change it to `https://youtu.be/abc123` and add `Http::fake()` in that test's `setUp()` so no real oEmbed request goes out:

```php
        Http::fake(['*' => Http::response(['thumbnail_url' => 'https://i.ytimg.com/vi/abc123/hq.jpg'])]);
```

- [ ] **Step 10: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Rules/AllowedVideoHost.php modules/WorkoutPlans/app/Services/VideoThumbnailResolver.php \
        modules/WorkoutPlans/config/config.php modules/WorkoutPlans/app/Http/Requests \
        modules/WorkoutPlans/app/Http/Controllers/Api/V1/ExerciseController.php \
        modules/WorkoutPlans/tests/Unit/AllowedVideoHostTest.php lang/it/validation.php lang/en/validation.php
git commit -m "feat(workoutplans): restrict exercise video urls to allowlisted hosts"
```

---

### Task 4: Fix `duplicate()` template inheritance

`WorkoutPlanController@duplicate` hardcodes `is_template = true` on the copy. Duplicating a client's plan therefore yields a template. The copy must inherit `is_template` from its source.

**Files:**
- Modify: `modules/WorkoutPlans/app/Http/Controllers/Api/V1/WorkoutPlanController.php:158`
- Test: `modules/WorkoutPlans/tests/Feature/Api/WorkoutPlanControllerTest.php`

**Interfaces:**
- Consumes: `WorkoutPlanPolicy` from Task 2.
- Produces: nothing new.

- [ ] **Step 1: Write the failing test**

Append to `modules/WorkoutPlans/tests/Feature/Api/WorkoutPlanControllerTest.php`:

```php
    public function test_duplicating_a_non_template_plan_yields_a_non_template(): void
    {
        $plan = WorkoutPlan::factory()->create([
            'coach_id' => $this->coach->id,
            'is_template' => false,
        ]);

        $response = $this->asCoach()->postJson("/api/v1/workout-plans/{$plan->id}/duplicate");

        $response->assertCreated();
        $this->assertDatabaseHas('workout_plans', [
            'id' => $response->json('data.id'),
            'is_template' => false,
        ]);
    }

    public function test_duplicating_a_template_yields_a_template(): void
    {
        $plan = WorkoutPlan::factory()->create([
            'coach_id' => $this->coach->id,
            'is_template' => true,
        ]);

        $response = $this->asCoach()->postJson("/api/v1/workout-plans/{$plan->id}/duplicate");

        $response->assertCreated();
        $this->assertDatabaseHas('workout_plans', [
            'id' => $response->json('data.id'),
            'is_template' => true,
        ]);
    }
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `php -d memory_limit=2048M artisan test --compact --filter=test_duplicating_a_non_template_plan_yields_a_non_template`

Expected: FAIL. The copy has `is_template = 1`; the test wants `0`.

- [ ] **Step 3: Fix it**

In `duplicate()`, delete this line:

```php
            $copy->is_template = true;
```

`replicate()` already carries `is_template` over from the source.

- [ ] **Step 4: Run both tests to verify they pass**

Run: `php -d memory_limit=2048M artisan test --compact --filter=WorkoutPlanControllerTest`

Expected: PASS. Watch for an older test that asserted the copy is a template — if one exists, it encoded the bug and must be updated, not the fix.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add modules/WorkoutPlans/app/Http/Controllers/Api/V1/WorkoutPlanController.php \
        modules/WorkoutPlans/tests/Feature/Api/WorkoutPlanControllerTest.php
git commit -m "fix(workoutplans): duplicated plan inherits is_template from source"
```

---

### Task 5: Plan tree DTOs

The save payload is the same shape on both HTTP surfaces and on the client. Define it once in PHP and generate the TypeScript from it.

**Files:**
- Create: `modules/WorkoutPlans/app/Data/PlanExerciseData.php`
- Create: `modules/WorkoutPlans/app/Data/PlanDayData.php`
- Create: `modules/WorkoutPlans/app/Data/PlanTreeData.php`
- Test: `modules/WorkoutPlans/tests/Unit/PlanTreeDataTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `PlanExerciseData::__construct(?int $id, int $exerciseId, ?int $sets, ?int $repsTarget, ?float $weightKg, ?int $rirTarget, ?int $restSeconds, ?string $notes)`
  - `PlanDayData::__construct(?int $id, int $dayNumber, ?string $name, array $exercises)` where `$exercises` is `PlanExerciseData[]`
  - `PlanTreeData::__construct(string $name, ?string $description, ?string $goal, ?string $difficulty, ?int $durationWeeks, ?int $sessionsPerWeek, bool $isTemplate, array $days)` where `$days` is `PlanDayData[]`
  - `PlanTreeData::fromArray(array $validated): self`
  - Generated TS namespace `Modules.WorkoutPlans.Data.*`

  Tasks 6, 8, and 13 depend on these exact names.

- [ ] **Step 1: Write the failing test**

Create `modules/WorkoutPlans/tests/Unit/PlanTreeDataTest.php`:

```php
<?php

namespace Modules\WorkoutPlans\Tests\Unit;

use Modules\WorkoutPlans\Data\PlanTreeData;
use PHPUnit\Framework\TestCase;

class PlanTreeDataTest extends TestCase
{
    public function test_it_builds_a_full_tree_from_validated_input(): void
    {
        $tree = PlanTreeData::fromArray([
            'name' => 'Forza Base',
            'description' => 'Quattro settimane.',
            'goal' => 'strength',
            'difficulty' => 'beginner',
            'durationWeeks' => 4,
            'sessionsPerWeek' => 3,
            'isTemplate' => true,
            'days' => [
                [
                    'id' => 7,
                    'dayNumber' => 1,
                    'name' => 'Push',
                    'exercises' => [
                        [
                            'id' => null,
                            'exerciseId' => 42,
                            'sets' => 4,
                            'repsTarget' => 8,
                            'weightKg' => 60.5,
                            'rirTarget' => 2,
                            'restSeconds' => 90,
                            'notes' => 'tempo 3-1-1',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('Forza Base', $tree->name);
        $this->assertTrue($tree->isTemplate);
        $this->assertCount(1, $tree->days);
        $this->assertSame(7, $tree->days[0]->id);
        $this->assertSame('Push', $tree->days[0]->name);
        $this->assertCount(1, $tree->days[0]->exercises);
        $this->assertNull($tree->days[0]->exercises[0]->id);
        $this->assertSame(42, $tree->days[0]->exercises[0]->exerciseId);
        $this->assertSame(60.5, $tree->days[0]->exercises[0]->weightKg);
    }

    public function test_it_defaults_absent_optional_fields(): void
    {
        $tree = PlanTreeData::fromArray(['name' => 'Minimo']);

        $this->assertSame('Minimo', $tree->name);
        $this->assertNull($tree->description);
        $this->assertFalse($tree->isTemplate);
        $this->assertSame([], $tree->days);
    }

    public function test_a_day_without_exercises_is_valid(): void
    {
        $tree = PlanTreeData::fromArray([
            'name' => 'Vuoto',
            'days' => [['id' => null, 'dayNumber' => 1, 'name' => null]],
        ]);

        $this->assertCount(1, $tree->days);
        $this->assertSame([], $tree->days[0]->exercises);
    }
}
```

This extends PHPUnit's `TestCase`, not Laravel's — these are pure value objects and need no application boot.

- [ ] **Step 2: Run it to make sure it fails**

Run: `php -d memory_limit=2048M artisan test --compact --filter=PlanTreeDataTest`

Expected: FAIL with `Class "Modules\WorkoutPlans\Data\PlanTreeData" not found`.

- [ ] **Step 3: Write the DTOs**

Create `modules/WorkoutPlans/app/Data/PlanExerciseData.php`:

```php
<?php

namespace Modules\WorkoutPlans\Data;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PlanExerciseData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $exerciseId,
        public readonly ?int $sets = null,
        public readonly ?int $repsTarget = null,
        public readonly ?float $weightKg = null,
        public readonly ?int $rirTarget = null,
        public readonly ?int $restSeconds = null,
        public readonly ?string $notes = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            exerciseId: (int) $data['exerciseId'],
            sets: isset($data['sets']) ? (int) $data['sets'] : null,
            repsTarget: isset($data['repsTarget']) ? (int) $data['repsTarget'] : null,
            weightKg: isset($data['weightKg']) ? (float) $data['weightKg'] : null,
            rirTarget: isset($data['rirTarget']) ? (int) $data['rirTarget'] : null,
            restSeconds: isset($data['restSeconds']) ? (int) $data['restSeconds'] : null,
            notes: $data['notes'] ?? null,
        );
    }
}
```

Create `modules/WorkoutPlans/app/Data/PlanDayData.php`:

```php
<?php

namespace Modules\WorkoutPlans\Data;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PlanDayData
{
    /** @param array<int, PlanExerciseData> $exercises */
    public function __construct(
        public readonly ?int $id,
        public readonly int $dayNumber,
        public readonly ?string $name = null,
        public readonly array $exercises = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            dayNumber: (int) $data['dayNumber'],
            name: $data['name'] ?? null,
            exercises: array_map(
                static fn (array $exercise): PlanExerciseData => PlanExerciseData::fromArray($exercise),
                $data['exercises'] ?? [],
            ),
        );
    }
}
```

Create `modules/WorkoutPlans/app/Data/PlanTreeData.php`:

```php
<?php

namespace Modules\WorkoutPlans\Data;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PlanTreeData
{
    /** @param array<int, PlanDayData> $days */
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly ?string $goal = null,
        public readonly ?string $difficulty = null,
        public readonly ?int $durationWeeks = null,
        public readonly ?int $sessionsPerWeek = null,
        public readonly bool $isTemplate = false,
        public readonly array $days = [],
    ) {}

    /** @param array<string, mixed> $validated */
    public static function fromArray(array $validated): self
    {
        return new self(
            name: $validated['name'],
            description: $validated['description'] ?? null,
            goal: $validated['goal'] ?? null,
            difficulty: $validated['difficulty'] ?? null,
            durationWeeks: isset($validated['durationWeeks']) ? (int) $validated['durationWeeks'] : null,
            sessionsPerWeek: isset($validated['sessionsPerWeek']) ? (int) $validated['sessionsPerWeek'] : null,
            isTemplate: (bool) ($validated['isTemplate'] ?? false),
            days: array_map(
                static fn (array $day): PlanDayData => PlanDayData::fromArray($day),
                $validated['days'] ?? [],
            ),
        );
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php -d memory_limit=2048M artisan test --compact --filter=PlanTreeDataTest`

Expected: PASS, 3 tests.

- [ ] **Step 5: Generate the TypeScript types**

Run: `php artisan module:generate-types WorkoutPlans`

Then open `modules/WorkoutPlans/resources/js/types/generated.d.ts` and confirm a new block appeared:

```typescript
declare namespace Modules.WorkoutPlans.Data {
export type PlanExerciseData = { id: number | null; exerciseId: number; ... };
export type PlanDayData = { id: number | null; dayNumber: number; name: string | null; exercises: Array<Modules.WorkoutPlans.Data.PlanExerciseData>; };
export type PlanTreeData = { name: string; ... days: Array<Modules.WorkoutPlans.Data.PlanDayData>; };
}
```

If `exercises` came out as `Array<any>`, the array-shape docblock is missing or malformed — fix the docblock, don't hand-edit the generated file. Note that with promoted constructor properties, `DtoTransformer` reads a `/** @var PlanExerciseData[] */` block placed directly above the promoted parameter; a constructor-level `@param array<int, PlanExerciseData>` does not reach it.

- [ ] **Step 6: Commit**

`generated.d.ts` is gitignored (`.gitignore` line 31: `/modules/*/resources/js/types/generated.d.ts`). It is a build artifact — do not add it.

```bash
vendor/bin/pint --dirty --format agent
git add modules/WorkoutPlans/app/Data modules/WorkoutPlans/tests/Unit/PlanTreeDataTest.php
git commit -m "feat(workoutplans): add plan tree dtos with generated typescript types"
```

Every later task that touches TypeScript must run `php artisan module:generate-types WorkoutPlans` before type-checking — a fresh checkout has no `generated.d.ts` and `vue-tsc` will fail without it.

---

### Task 6: `WorkoutPlanWriter`

The heart of the feature. One transactional method that reconciles the stored tree against the submitted tree, preserving `workout_exercises.id` across saves.

**Files:**
- Create: `modules/WorkoutPlans/app/Services/WorkoutPlanWriter.php`
- Test: `modules/WorkoutPlans/tests/Unit/WorkoutPlanWriterTest.php`

**Interfaces:**
- Consumes: `PlanTreeData` (Task 5), `WorkoutExerciseFactory` (Task 1).
- Produces:
  - `WorkoutPlanWriter::write(Coach $coach, ?WorkoutPlan $plan, PlanTreeData $data): WorkoutPlan`
  - `WorkoutPlanWriter::syncAssignments(WorkoutPlan $plan, array $clientIds, ?CarbonInterface $activeFrom): void`

  Tasks 7 and 8 call both.

- [ ] **Step 1: Write the failing test**

Create `modules/WorkoutPlans/tests/Unit/WorkoutPlanWriterTest.php`. Despite the `Unit` directory it needs the database, so it extends Laravel's `TestCase`:

```php
<?php

namespace Modules\WorkoutPlans\Tests\Unit;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\WorkoutPlans\Data\PlanDayData;
use Modules\WorkoutPlans\Data\PlanExerciseData;
use Modules\WorkoutPlans\Data\PlanTreeData;
use Modules\WorkoutPlans\Models\Exercise;
use Modules\WorkoutPlans\Models\WorkoutPlan;
use Modules\WorkoutPlans\Services\WorkoutPlanWriter;
use Tests\TestCase;

class WorkoutPlanWriterTest extends TestCase
{
    use RefreshDatabase;

    private WorkoutPlanWriter $writer;

    private Coach $coach;

    private Exercise $squat;

    private Exercise $bench;

    protected function setUp(): void
    {
        parent::setUp();

        $this->writer = app(WorkoutPlanWriter::class);
        $this->coach = Coach::factory()->create();
        $this->squat = Exercise::factory()->global()->create(['name' => 'Squat']);
        $this->bench = Exercise::factory()->global()->create(['name' => 'Bench']);
    }

    private function tree(array $days, string $name = 'Piano'): PlanTreeData
    {
        return new PlanTreeData(name: $name, days: $days);
    }

    public function test_it_creates_a_plan_with_days_and_exercises(): void
    {
        $plan = $this->writer->write($this->coach, null, $this->tree([
            new PlanDayData(id: null, dayNumber: 1, name: 'Push', exercises: [
                new PlanExerciseData(id: null, exerciseId: $this->squat->id, sets: 3, repsTarget: 10),
            ]),
        ]));

        $this->assertSame($this->coach->id, $plan->coach_id);
        $this->assertCount(1, $plan->workoutDays);
        $this->assertCount(1, $plan->workoutDays[0]->workoutExercises);
        $this->assertSame(0, $plan->workoutDays[0]->workoutExercises[0]->order_index);
    }

    public function test_it_preserves_exercise_ids_across_saves(): void
    {
        $plan = $this->writer->write($this->coach, null, $this->tree([
            new PlanDayData(id: null, dayNumber: 1, name: 'Push', exercises: [
                new PlanExerciseData(id: null, exerciseId: $this->squat->id, sets: 3),
            ]),
        ]));

        $dayId = $plan->workoutDays[0]->id;
        $exerciseRowId = $plan->workoutDays[0]->workoutExercises[0]->id;

        $plan = $this->writer->write($this->coach, $plan, $this->tree([
            new PlanDayData(id: $dayId, dayNumber: 1, name: 'Push', exercises: [
                new PlanExerciseData(id: $exerciseRowId, exerciseId: $this->squat->id, sets: 5),
            ]),
        ]));

        $row = $plan->workoutDays[0]->workoutExercises[0];
        $this->assertSame($exerciseRowId, $row->id, 'The workout_exercises row must survive a save.');
        $this->assertSame(5, $row->sets);
    }

    public function test_order_index_follows_array_position(): void
    {
        $plan = $this->writer->write($this->coach, null, $this->tree([
            new PlanDayData(id: null, dayNumber: 1, name: 'Push', exercises: [
                new PlanExerciseData(id: null, exerciseId: $this->squat->id),
                new PlanExerciseData(id: null, exerciseId: $this->bench->id),
            ]),
        ]));

        $day = $plan->workoutDays[0];
        $squatRow = $day->workoutExercises->firstWhere('exercise_id', $this->squat->id);
        $benchRow = $day->workoutExercises->firstWhere('exercise_id', $this->bench->id);

        $this->assertSame(0, $squatRow->order_index);
        $this->assertSame(1, $benchRow->order_index);

        // Swap them and save again.
        $plan = $this->writer->write($this->coach, $plan, $this->tree([
            new PlanDayData(id: $day->id, dayNumber: 1, name: 'Push', exercises: [
                new PlanExerciseData(id: $benchRow->id, exerciseId: $this->bench->id),
                new PlanExerciseData(id: $squatRow->id, exerciseId: $this->squat->id),
            ]),
        ]));

        $day = $plan->workoutDays[0];
        $this->assertSame(0, $day->workoutExercises->firstWhere('exercise_id', $this->bench->id)->order_index);
        $this->assertSame(1, $day->workoutExercises->firstWhere('exercise_id', $this->squat->id)->order_index);
    }

    public function test_it_deletes_exercises_absent_from_the_payload(): void
    {
        $plan = $this->writer->write($this->coach, null, $this->tree([
            new PlanDayData(id: null, dayNumber: 1, name: 'Push', exercises: [
                new PlanExerciseData(id: null, exerciseId: $this->squat->id),
                new PlanExerciseData(id: null, exerciseId: $this->bench->id),
            ]),
        ]));

        $day = $plan->workoutDays[0];
        $keep = $day->workoutExercises->firstWhere('exercise_id', $this->squat->id);
        $drop = $day->workoutExercises->firstWhere('exercise_id', $this->bench->id);

        $this->writer->write($this->coach, $plan, $this->tree([
            new PlanDayData(id: $day->id, dayNumber: 1, name: 'Push', exercises: [
                new PlanExerciseData(id: $keep->id, exerciseId: $this->squat->id),
            ]),
        ]));

        $this->assertDatabaseHas('workout_exercises', ['id' => $keep->id]);
        $this->assertDatabaseMissing('workout_exercises', ['id' => $drop->id]);
    }

    public function test_it_deletes_days_absent_from_the_payload_and_their_exercises(): void
    {
        $plan = $this->writer->write($this->coach, null, $this->tree([
            new PlanDayData(id: null, dayNumber: 1, name: 'Push', exercises: [
                new PlanExerciseData(id: null, exerciseId: $this->squat->id),
            ]),
            new PlanDayData(id: null, dayNumber: 2, name: 'Pull', exercises: [
                new PlanExerciseData(id: null, exerciseId: $this->bench->id),
            ]),
        ]));

        $keepDay = $plan->workoutDays->firstWhere('day_number', 1);
        $dropDay = $plan->workoutDays->firstWhere('day_number', 2);
        $dropExerciseId = $dropDay->workoutExercises[0]->id;

        $this->writer->write($this->coach, $plan, $this->tree([
            new PlanDayData(id: $keepDay->id, dayNumber: 1, name: 'Push', exercises: [
                new PlanExerciseData(id: $keepDay->workoutExercises[0]->id, exerciseId: $this->squat->id),
            ]),
        ]));

        $this->assertDatabaseMissing('workout_days', ['id' => $dropDay->id]);
        $this->assertDatabaseMissing('workout_exercises', ['id' => $dropExerciseId]);
    }

    public function test_it_moves_an_exercise_between_days(): void
    {
        $plan = $this->writer->write($this->coach, null, $this->tree([
            new PlanDayData(id: null, dayNumber: 1, name: 'A', exercises: [
                new PlanExerciseData(id: null, exerciseId: $this->squat->id),
            ]),
            new PlanDayData(id: null, dayNumber: 2, name: 'B', exercises: []),
        ]));

        $dayA = $plan->workoutDays->firstWhere('day_number', 1);
        $dayB = $plan->workoutDays->firstWhere('day_number', 2);
        $rowId = $dayA->workoutExercises[0]->id;

        $plan = $this->writer->write($this->coach, $plan, $this->tree([
            new PlanDayData(id: $dayA->id, dayNumber: 1, name: 'A', exercises: []),
            new PlanDayData(id: $dayB->id, dayNumber: 2, name: 'B', exercises: [
                new PlanExerciseData(id: $rowId, exerciseId: $this->squat->id),
            ]),
        ]));

        $this->assertDatabaseHas('workout_exercises', [
            'id' => $rowId,
            'workout_day_id' => $dayB->id,
        ]);
    }

    public function test_it_does_not_touch_assignments(): void
    {
        $client = Client::factory()->create();
        $plan = $this->writer->write($this->coach, null, $this->tree([]));
        $this->writer->syncAssignments($plan, [$client->id], null);

        $this->writer->write($this->coach, $plan, $this->tree([], name: 'Rinominato'));

        $this->assertDatabaseHas('plan_assignments', [
            'workout_plan_id' => $plan->id,
            'client_id' => $client->id,
        ]);
    }

    public function test_sync_assignments_adds_and_removes(): void
    {
        $keep = Client::factory()->create();
        $drop = Client::factory()->create();
        $plan = $this->writer->write($this->coach, null, $this->tree([]));

        $this->writer->syncAssignments($plan, [$keep->id, $drop->id], null);
        $this->assertDatabaseCount('plan_assignments', 2);

        $this->writer->syncAssignments($plan, [$keep->id], null);

        $this->assertDatabaseHas('plan_assignments', ['workout_plan_id' => $plan->id, 'client_id' => $keep->id]);
        $this->assertDatabaseMissing('plan_assignments', ['workout_plan_id' => $plan->id, 'client_id' => $drop->id]);
    }

    public function test_a_failed_write_rolls_back_completely(): void
    {
        $plan = $this->writer->write($this->coach, null, $this->tree([
            new PlanDayData(id: null, dayNumber: 1, name: 'Push', exercises: [
                new PlanExerciseData(id: null, exerciseId: $this->squat->id),
            ]),
        ]));

        try {
            // exercise_id 999999 violates the foreign key.
            $this->writer->write($this->coach, $plan, $this->tree([
                new PlanDayData(id: $plan->workoutDays[0]->id, dayNumber: 1, name: 'Rinominato', exercises: [
                    new PlanExerciseData(id: null, exerciseId: 999999),
                ]),
            ]));
            $this->fail('Expected the write to throw.');
        } catch (\Throwable) {
            // expected
        }

        $this->assertDatabaseHas('workout_plans', ['id' => $plan->id, 'name' => 'Piano']);
        $this->assertDatabaseCount('workout_exercises', 1);
    }
}
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `php -d memory_limit=2048M artisan test --compact --filter=WorkoutPlanWriterTest`

Expected: FAIL with `Target class [Modules\WorkoutPlans\Services\WorkoutPlanWriter] does not exist.`

- [ ] **Step 3: Write the service**

Create `modules/WorkoutPlans/app/Services/WorkoutPlanWriter.php`:

```php
<?php

namespace Modules\WorkoutPlans\Services;

use App\Modules\Users\Models\Coach;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\WorkoutPlans\Data\PlanDayData;
use Modules\WorkoutPlans\Data\PlanTreeData;
use Modules\WorkoutPlans\Models\PlanAssignment;
use Modules\WorkoutPlans\Models\WorkoutDay;
use Modules\WorkoutPlans\Models\WorkoutExercise;
use Modules\WorkoutPlans\Models\WorkoutPlan;

class WorkoutPlanWriter
{
    /**
     * Reconcile the stored plan tree against the submitted one.
     *
     * Rows are matched by id so that workout_exercises.id survives a save;
     * rows absent from the payload are deleted. Assignments are untouched —
     * see syncAssignments().
     */
    public function write(Coach $coach, ?WorkoutPlan $plan, PlanTreeData $data): WorkoutPlan
    {
        return DB::transaction(function () use ($coach, $plan, $data): WorkoutPlan {
            $attributes = [
                'name' => $data->name,
                'description' => $data->description,
                'goal' => $data->goal,
                'difficulty' => $data->difficulty,
                'duration_weeks' => $data->durationWeeks,
                'sessions_per_week' => $data->sessionsPerWeek,
                'is_template' => $data->isTemplate,
            ];

            if ($plan === null) {
                $plan = WorkoutPlan::create($attributes + [
                    'coach_id' => $coach->id,
                    'is_active' => true,
                ]);
            } else {
                $plan->update($attributes);
            }

            $this->syncDays($plan, $data->days);

            return $plan->fresh(['workoutDays.workoutExercises.exercise']);
        });
    }

    /** @param array<int, PlanDayData> $days */
    private function syncDays(WorkoutPlan $plan, array $days): void
    {
        $keptDayIds = [];
        $keptExerciseRowIds = [];

        // Upsert every day and its exercises across the whole payload BEFORE
        // any deletion runs. An exercise moving from day A to day B in the same
        // save is still "kept" even though day A no longer lists it — a
        // per-day cleanup inside this loop would delete it out from under the
        // move, before day B's turn to reparent it.
        foreach ($days as $position => $dayData) {
            $day = $this->upsertDay($plan, $dayData, $position);
            $keptDayIds[] = $day->id;

            array_push($keptExerciseRowIds, ...$this->upsertExercises($plan, $day, $dayData));
        }

        // Plan-scoped, not day-scoped. Two things depend on this:
        // a row reparented above must not be caught by its old day's cleanup,
        // and a dropped day's exercises must still be reachable — workout_day_id
        // is nullOnDelete, so deleting the day would only orphan them, leaving
        // live rows attached to the plan that the builder never shows again.
        WorkoutExercise::query()
            ->where('workout_plan_id', $plan->id)
            ->whereNotIn('id', $keptExerciseRowIds ?: [0])
            ->delete();

        $plan->workoutDays()->whereNotIn('id', $keptDayIds ?: [0])->delete();
    }

    private function upsertDay(WorkoutPlan $plan, PlanDayData $dayData, int $position): WorkoutDay
    {
        $attributes = [
            'workout_plan_id' => $plan->id,
            'day_number' => $dayData->dayNumber,
            'name' => $dayData->name,
            'order_index' => $position,
        ];

        if ($dayData->id === null) {
            return WorkoutDay::create($attributes);
        }

        $day = $plan->workoutDays()->whereKey($dayData->id)->firstOrFail();
        $day->update($attributes);

        return $day;
    }

    /** @return array<int, int> the ids of the rows this day kept or created */
    private function upsertExercises(WorkoutPlan $plan, WorkoutDay $day, PlanDayData $dayData): array
    {
        $keptExerciseRowIds = [];

        foreach ($dayData->exercises as $position => $exerciseData) {
            $attributes = [
                'workout_plan_id' => $plan->id,
                'workout_day_id' => $day->id,
                'exercise_id' => $exerciseData->exerciseId,
                'sets' => $exerciseData->sets,
                'reps_target' => $exerciseData->repsTarget,
                'weight_kg' => $exerciseData->weightKg,
                'rir_target' => $exerciseData->rirTarget,
                'rest_seconds' => $exerciseData->restSeconds,
                'notes' => $exerciseData->notes,
                'order_index' => $position,
            ];

            if ($exerciseData->id === null) {
                $row = WorkoutExercise::create($attributes);
            } else {
                // Scoped to the plan, not the day: an exercise can move between days.
                $row = WorkoutExercise::query()
                    ->where('workout_plan_id', $plan->id)
                    ->whereKey($exerciseData->id)
                    ->firstOrFail();
                $row->update($attributes);
            }

            $keptExerciseRowIds[] = $row->id;
        }

        return $keptExerciseRowIds;
    }

    /**
     * Sync plan_assignments against the selected clients.
     * Called only on "Save and Assign", never on auto-save.
     *
     * @param array<int, int> $clientIds
     */
    public function syncAssignments(WorkoutPlan $plan, array $clientIds, ?CarbonInterface $activeFrom): void
    {
        DB::transaction(function () use ($plan, $clientIds, $activeFrom): void {
            foreach ($clientIds as $clientId) {
                PlanAssignment::updateOrCreate(
                    ['workout_plan_id' => $plan->id, 'client_id' => $clientId],
                    ['active_from' => $activeFrom, 'assigned_at' => now()],
                );
            }

            PlanAssignment::query()
                ->where('workout_plan_id', $plan->id)
                ->whereNotIn('client_id', $clientIds ?: [0])
                ->delete();
        });
    }
}
```

The `?: [0]` guards matter. `whereNotIn('id', [])` produces `WHERE id NOT IN ()`, which is a syntax error on MySQL. With an empty kept-list, every row should be deleted, and `NOT IN (0)` does exactly that since no id is 0.

The exercise lookup is scoped by `workout_plan_id` rather than `workout_day_id` on purpose: dragging an exercise from Monday to Wednesday sends the same row id under a different day, and the row must be found and moved, not rejected.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php -d memory_limit=2048M artisan test --compact --filter=WorkoutPlanWriterTest`

Expected: PASS, 9 tests.

If `test_a_failed_write_rolls_back_completely` passes without throwing, foreign keys are off in the SQLite test connection. Check that `config/database.php`'s `sqlite` connection has `'foreign_key_constraints' => true`. Do not weaken the test.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add modules/WorkoutPlans/app/Services/WorkoutPlanWriter.php \
        modules/WorkoutPlans/tests/Unit/WorkoutPlanWriterTest.php
git commit -m "feat(workoutplans): add transactional plan tree writer"
```

---

### Task 7: Route the API through the writer

`Api\V1\WorkoutPlanController@store` builds the tree by hand and `@update` ignores days entirely. Point both at the writer so the mobile app and the web builder share one persistence path.

**Files:**
- Modify: `modules/WorkoutPlans/app/Http/Controllers/Api/V1/WorkoutPlanController.php`
- Test: `modules/WorkoutPlans/tests/Feature/Api/WorkoutPlanControllerTest.php`

**Interfaces:**
- Consumes: `WorkoutPlanWriter` (Task 6), `PlanTreeData` (Task 5).
- Produces: no signature changes. `PUT /api/v1/workout-plans/{plan}` now accepts an optional `days` key; when present it replaces the tree, when absent the tree is untouched.

- [ ] **Step 1: Write the failing test**

Append to `modules/WorkoutPlans/tests/Feature/Api/WorkoutPlanControllerTest.php`:

```php
    public function test_updating_a_plan_without_days_leaves_the_tree_intact(): void
    {
        $exercise = Exercise::factory()->global()->create();
        $plan = WorkoutPlan::factory()->create(['coach_id' => $this->coach->id, 'name' => 'Vecchio']);
        $day = WorkoutDay::factory()->create(['workout_plan_id' => $plan->id, 'day_number' => 1]);
        WorkoutExercise::factory()->create([
            'workout_plan_id' => $plan->id,
            'workout_day_id' => $day->id,
            'exercise_id' => $exercise->id,
        ]);

        $this->asCoach()->putJson("/api/v1/workout-plans/{$plan->id}", ['name' => 'Nuovo'])
            ->assertOk();

        $this->assertDatabaseHas('workout_plans', ['id' => $plan->id, 'name' => 'Nuovo']);
        $this->assertDatabaseCount('workout_exercises', 1);
        $this->assertDatabaseCount('workout_days', 1);
    }

    public function test_updating_a_plan_with_days_replaces_the_tree(): void
    {
        $exercise = Exercise::factory()->global()->create();
        $plan = WorkoutPlan::factory()->create(['coach_id' => $this->coach->id]);
        $day = WorkoutDay::factory()->create(['workout_plan_id' => $plan->id, 'day_number' => 1]);
        $row = WorkoutExercise::factory()->create([
            'workout_plan_id' => $plan->id,
            'workout_day_id' => $day->id,
            'exercise_id' => $exercise->id,
        ]);

        $this->asCoach()->putJson("/api/v1/workout-plans/{$plan->id}", [
            'name' => $plan->name,
            'days' => [
                [
                    'id' => $day->id,
                    'dayNumber' => 1,
                    'name' => 'Rinominato',
                    'exercises' => [
                        ['id' => $row->id, 'exerciseId' => $exercise->id, 'sets' => 5],
                    ],
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('workout_days', ['id' => $day->id, 'name' => 'Rinominato']);
        $this->assertDatabaseHas('workout_exercises', ['id' => $row->id, 'sets' => 5]);
    }
```

- [ ] **Step 2: Run them to make sure they fail**

Run: `php -d memory_limit=2048M artisan test --compact --filter=test_updating_a_plan_with_days_replaces_the_tree`

Expected: FAIL. Today `update()` only writes plan attributes; `workout_days.name` stays unchanged.

- [ ] **Step 3: Rewrite `store()` and `update()`**

Add to `UpdateWorkoutPlanRequest::rules()` the same nested `days.*` rules that `StoreWorkoutPlanRequest` has, plus the row ids:

```php
            'days' => ['sometimes', 'array', 'max:7'],
            'days.*.id' => ['nullable', 'integer'],
            'days.*.dayNumber' => ['required_with:days', 'integer', 'min:1', 'max:31'],
            'days.*.name' => ['nullable', 'string', 'max:255'],
            'days.*.exercises' => ['nullable', 'array', 'max:50'],
            'days.*.exercises.*.id' => ['nullable', 'integer'],
            'days.*.exercises.*.exerciseId' => ['required_with:days.*.exercises', 'integer', 'exists:exercises,id'],
            'days.*.exercises.*.sets' => ['nullable', 'integer', 'min:1', 'max:20'],
            'days.*.exercises.*.repsTarget' => ['nullable', 'integer', 'min:1', 'max:100'],
            'days.*.exercises.*.weightKg' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'days.*.exercises.*.rirTarget' => ['nullable', 'integer', 'min:0', 'max:10'],
            'days.*.exercises.*.restSeconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'days.*.exercises.*.notes' => ['nullable', 'string', 'max:1000'],
```

Add `'days.*.id'` and `'days.*.exercises.*.id'` to `StoreWorkoutPlanRequest` too (always null on create, but the shape stays uniform).

Then in `WorkoutPlanController`, add the constructor:

```php
    public function __construct(private WorkoutPlanWriter $writer) {}
```

Replace the body of `store()` after the coach guard with:

```php
        $plan = $this->writer->write($coach, null, PlanTreeData::fromArray($request->validated()));

        return response()->json(['data' => new WorkoutPlanDetailResource($plan)], 201);
```

Replace the body of `update()` after the authorization check with:

```php
        if ($request->has('days')) {
            $plan = $this->writer->write($request->user()->coach, $workoutPlan, PlanTreeData::fromArray($request->validated()));

            return response()->json(['data' => new WorkoutPlanDetailResource($plan)]);
        }

        $workoutPlan->update(array_filter([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'goal' => $request->validated('goal'),
            'difficulty' => $request->validated('difficulty'),
            'duration_weeks' => $request->validated('durationWeeks'),
            'sessions_per_week' => $request->validated('sessionsPerWeek'),
            'is_template' => $request->has('isTemplate') ? $request->boolean('isTemplate') : null,
            'is_active' => $request->has('isActive') ? $request->boolean('isActive') : null,
        ], fn ($v) => $v !== null));

        return response()->json(['data' => new WorkoutPlanDetailResource(
            $workoutPlan->fresh()->load(['workoutDays.workoutExercises.exercise'])
        )]);
```

The partial-update path stays for the mobile app, which sends `PATCH`-style bodies without `days`. Only a body carrying `days` goes through the writer.

Delete the now-unused `WorkoutDay` and `WorkoutExercise` imports if nothing else in the file uses them (`duplicate()` does not — it uses relations).

Add imports: `Modules\WorkoutPlans\Data\PlanTreeData`, `Modules\WorkoutPlans\Services\WorkoutPlanWriter`.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php -d memory_limit=2048M artisan test --compact --filter=WorkoutPlanControllerTest`

Expected: PASS, including the pre-existing `test_coach_can_create_workout_plan_with_days_and_exercises`.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add modules/WorkoutPlans/app/Http/Controllers/Api/V1/WorkoutPlanController.php \
        modules/WorkoutPlans/app/Http/Requests \
        modules/WorkoutPlans/tests/Feature/Api/WorkoutPlanControllerTest.php
git commit -m "refactor(workoutplans): route api plan writes through WorkoutPlanWriter"
```

---

### Task 8: Coach web routes and `PlanBuilderController`

The Inertia surface. Returns JSON from `store`/`update` so the auto-save never remounts the page.

**Files:**
- Create: `modules/WorkoutPlans/app/Http/Controllers/Coach/PlanBuilderController.php`
- Create: `modules/WorkoutPlans/app/Http/Requests/StoreCoachPlanRequest.php`
- Create: `modules/WorkoutPlans/app/Http/Requests/UpdateCoachPlanRequest.php`
- Modify: `modules/WorkoutPlans/routes/web.php`
- Modify: `modules/WorkoutPlans/routes/navigation.php`
- Delete: `modules/WorkoutPlans/app/Http/Controllers/WorkoutPlansController.php`
- Test: `modules/WorkoutPlans/tests/Feature/Coach/PlanBuilderControllerTest.php`

**Interfaces:**
- Consumes: `WorkoutPlanWriter`, `PlanTreeData`, `WorkoutPlanPolicy`.
- Produces: named routes `coach.plans.index|create|edit|store|update|duplicate|destroy`. `store` returns `201 {plan: {...}}`; `update` returns `200 {plan: {...}}`. Task 13's composable posts to these.

- [ ] **Step 1: Write the failing test**

Create `modules/WorkoutPlans/tests/Feature/Coach/PlanBuilderControllerTest.php`:

```php
<?php

namespace Modules\WorkoutPlans\Tests\Feature\Coach;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\WorkoutPlans\Models\Exercise;
use Modules\WorkoutPlans\Models\WorkoutPlan;
use Tests\TestCase;

class PlanBuilderControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Coach $coach;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->user->assignRole(Role::COACH);
        $this->coach = Coach::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_guests_are_redirected_from_the_builder(): void
    {
        $this->get(route('coach.plans.create'))->assertRedirect();
    }

    public function test_a_client_cannot_reach_the_builder(): void
    {
        $client = User::factory()->create(['email_verified_at' => now()]);
        $client->assignRole(Role::CLIENT);

        $this->actingAs($client)->get(route('coach.plans.create'))->assertForbidden();
    }

    public function test_coach_sees_the_plan_list(): void
    {
        WorkoutPlan::factory()->create(['coach_id' => $this->coach->id, 'name' => 'Forza Base']);

        $this->actingAs($this->user)
            ->get(route('coach.plans.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkoutPlans::Coach/Plans/Index')
                ->has('plans', 1)
                ->where('plans.0.name', 'Forza Base')
                ->has('stats.plans')
                ->has('stats.exercises')
            );
    }

    public function test_the_plan_list_excludes_other_coaches_plans(): void
    {
        $other = Coach::factory()->create();
        WorkoutPlan::factory()->create(['coach_id' => $other->id]);

        $this->actingAs($this->user)
            ->get(route('coach.plans.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('plans', 0));
    }

    public function test_storing_a_plan_returns_json_with_the_new_id(): void
    {
        $exercise = Exercise::factory()->global()->create();

        $response = $this->actingAs($this->user)->postJson(route('coach.plans.store'), [
            'name' => 'Forza Base',
            'days' => [
                [
                    'id' => null,
                    'dayNumber' => 1,
                    'name' => 'Push',
                    'exercises' => [
                        ['id' => null, 'exerciseId' => $exercise->id, 'sets' => 4, 'repsTarget' => 8],
                    ],
                ],
            ],
        ]);

        $response->assertCreated()->assertJsonStructure(['plan' => ['id', 'name', 'days']]);
        $this->assertDatabaseHas('workout_plans', ['name' => 'Forza Base', 'coach_id' => $this->coach->id]);
    }

    public function test_a_coach_cannot_put_another_coachs_custom_exercise_in_a_plan(): void
    {
        $otherCoach = Coach::factory()->create();
        $theirExercise = Exercise::factory()->create(['coach_id' => $otherCoach->id]);

        $this->actingAs($this->user)->postJson(route('coach.plans.store'), [
            'name' => 'Furto',
            'days' => [[
                'id' => null,
                'dayNumber' => 1,
                'exercises' => [['id' => null, 'exerciseId' => $theirExercise->id]],
            ]],
        ])->assertUnprocessable()->assertJsonValidationErrorFor('days.0.exercises.0.exerciseId');
    }

    public function test_a_coach_can_use_a_global_exercise(): void
    {
        $global = Exercise::factory()->global()->create();

        $this->actingAs($this->user)->postJson(route('coach.plans.store'), [
            'name' => 'Ok',
            'days' => [[
                'id' => null,
                'dayNumber' => 1,
                'exercises' => [['id' => null, 'exerciseId' => $global->id]],
            ]],
        ])->assertCreated();
    }

    public function test_updating_another_coachs_plan_is_forbidden(): void
    {
        $other = Coach::factory()->create();
        $plan = WorkoutPlan::factory()->create(['coach_id' => $other->id]);

        $this->actingAs($this->user)
            ->putJson(route('coach.plans.update', $plan), ['name' => 'Preso'])
            ->assertForbidden();
    }

    public function test_assigning_to_a_client_who_is_not_an_athlete_is_rejected(): void
    {
        $stranger = Client::factory()->create();
        $plan = WorkoutPlan::factory()->create(['coach_id' => $this->coach->id]);

        $this->actingAs($this->user)
            ->putJson(route('coach.plans.update', $plan), [
                'name' => $plan->name,
                'clientIds' => [$stranger->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('clientIds.0');
    }

    public function test_assigning_to_an_athlete_creates_the_assignment(): void
    {
        $athlete = Client::factory()->create();
        $this->coach->athletes()->attach($athlete->id, ['status' => 'active']);
        $plan = WorkoutPlan::factory()->create(['coach_id' => $this->coach->id]);

        $this->actingAs($this->user)
            ->putJson(route('coach.plans.update', $plan), [
                'name' => $plan->name,
                'clientIds' => [$athlete->id],
            ])
            ->assertOk();

        $this->assertDatabaseHas('plan_assignments', [
            'workout_plan_id' => $plan->id,
            'client_id' => $athlete->id,
        ]);
    }

    public function test_an_update_without_clientIds_leaves_assignments_alone(): void
    {
        $athlete = Client::factory()->create();
        $this->coach->athletes()->attach($athlete->id, ['status' => 'active']);
        $plan = WorkoutPlan::factory()->create(['coach_id' => $this->coach->id]);

        $this->actingAs($this->user)->putJson(route('coach.plans.update', $plan), [
            'name' => $plan->name,
            'clientIds' => [$athlete->id],
        ])->assertOk();

        // An auto-save omits clientIds entirely.
        $this->actingAs($this->user)->putJson(route('coach.plans.update', $plan), [
            'name' => 'Auto-salvato',
        ])->assertOk();

        $this->assertDatabaseHas('plan_assignments', [
            'workout_plan_id' => $plan->id,
            'client_id' => $athlete->id,
        ]);
    }
}
```

The last test pins the rule that auto-save must never touch assignments.

- [ ] **Step 2: Run it to make sure it fails**

Run: `php -d memory_limit=2048M artisan test --compact --filter=PlanBuilderControllerTest`

Expected: FAIL with `Route [coach.plans.create] not defined.`

- [ ] **Step 3: Write the form requests**

The two requests share the bulk of their rules but differ on one security-relevant point: child-row ids. On **create** there are no existing rows to reference, so any `days[].id` or `exercises[].id` is meaningless and must be rejected. On **update** an id may name an existing row, but only one belonging to *this* plan — a foreign id must be a clean 422, not a row from another coach's plan (see the IDOR note below). So a shared base holds the common rules and each subclass supplies its own id rules.

Create `modules/WorkoutPlans/app/Http/Requests/CoachPlanRequest.php`:

```php
<?php

namespace Modules\WorkoutPlans\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class CoachPlanRequest extends FormRequest
{
    /**
     * Rules for `days.*.id` and `days.*.exercises.*.id`.
     *
     * @return array{day: array<int, mixed>, exercise: array<int, mixed>}
     */
    abstract protected function childIdRules(): array;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $coachId = $this->user()->coach?->id;
        $childIds = $this->childIdRules();

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'goal' => ['nullable', 'string', 'max:50'],
            'difficulty' => ['nullable', 'string', 'max:50'],
            'durationWeeks' => ['nullable', 'integer', 'min:1', 'max:52'],
            'sessionsPerWeek' => ['nullable', 'integer', 'min:1', 'max:7'],
            'isTemplate' => ['boolean'],

            'days' => ['nullable', 'array', 'max:7'],
            'days.*.id' => $childIds['day'],
            'days.*.dayNumber' => ['required_with:days', 'integer', 'min:1', 'max:31'],
            'days.*.name' => ['nullable', 'string', 'max:255'],

            'days.*.exercises' => ['nullable', 'array', 'max:50'],
            'days.*.exercises.*.id' => $childIds['exercise'],
            'days.*.exercises.*.exerciseId' => [
                'required_with:days.*.exercises',
                'integer',
                // Global exercises, or this coach's own. Never another coach's.
                Rule::exists('exercises', 'id')->where(
                    fn ($query) => $query->whereNull('coach_id')->orWhere('coach_id', $coachId)
                ),
            ],
            'days.*.exercises.*.sets' => ['nullable', 'integer', 'min:1', 'max:20'],
            'days.*.exercises.*.repsTarget' => ['nullable', 'integer', 'min:1', 'max:100'],
            'days.*.exercises.*.weightKg' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'days.*.exercises.*.rirTarget' => ['nullable', 'integer', 'min:0', 'max:10'],
            'days.*.exercises.*.restSeconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'days.*.exercises.*.notes' => ['nullable', 'string', 'max:1000'],

            'clientIds' => ['sometimes', 'array'],
            'clientIds.*' => [
                'integer',
                // Athletes of this coach, through the athlete_coach pivot.
                Rule::exists('athlete_coach', 'athlete_id')->where('coach_id', $coachId),
            ],
            'activeFrom' => ['nullable', 'date'],
        ];
    }
}
```

Create `modules/WorkoutPlans/app/Http/Requests/StoreCoachPlanRequest.php`:

```php
<?php

namespace Modules\WorkoutPlans\Http\Requests;

class StoreCoachPlanRequest extends CoachPlanRequest
{
    /**
     * On create there is no existing row to reference. `prohibited` still
     * accepts a null (the builder sends `id: null` for uniform shape) but
     * rejects any real id, so a client cannot smuggle another plan's row id
     * into a brand-new plan.
     *
     * @return array{day: array<int, mixed>, exercise: array<int, mixed>}
     */
    protected function childIdRules(): array
    {
        return [
            'day' => ['prohibited'],
            'exercise' => ['prohibited'],
        ];
    }
}
```

Create `modules/WorkoutPlans/app/Http/Requests/UpdateCoachPlanRequest.php`:

```php
<?php

namespace Modules\WorkoutPlans\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateCoachPlanRequest extends CoachPlanRequest
{
    /**
     * On update an id may name an existing row, but only one belonging to the
     * plan being saved. A foreign id fails validation with a clean 422 rather
     * than reaching the writer (which would throw and roll back with a 500).
     * The route-model-bound plan is `{plan}`.
     *
     * @return array{day: array<int, mixed>, exercise: array<int, mixed>}
     */
    protected function childIdRules(): array
    {
        $planId = $this->route('plan')?->id;

        return [
            'day' => [
                'nullable', 'integer',
                Rule::exists('workout_days', 'id')->where('workout_plan_id', $planId),
            ],
            'exercise' => [
                'nullable', 'integer',
                Rule::exists('workout_exercises', 'id')->where('workout_plan_id', $planId),
            ],
        ];
    }
}
```

- [ ] **Step 4: Write the controller**

Create `modules/WorkoutPlans/app/Http/Controllers/Coach/PlanBuilderController.php`:

```php
<?php

namespace Modules\WorkoutPlans\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Modules\WorkoutPlans\Data\PlanTreeData;
use Modules\WorkoutPlans\Http\Requests\StoreCoachPlanRequest;
use Modules\WorkoutPlans\Http\Requests\UpdateCoachPlanRequest;
use Modules\WorkoutPlans\Http\Resources\WorkoutPlanDetailResource;
use Modules\WorkoutPlans\Models\Exercise;
use Modules\WorkoutPlans\Models\WorkoutPlan;
use Modules\WorkoutPlans\Services\WorkoutPlanWriter;

class PlanBuilderController extends Controller
{
    public function __construct(private WorkoutPlanWriter $writer) {}

    public function index(Request $request): Response
    {
        $coach = $request->user()->coach;

        $plans = WorkoutPlan::forCoach($coach->id)
            ->withCount('workoutDays')
            ->latest()
            ->get()
            ->map(fn (WorkoutPlan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
                'goal' => $plan->goal?->value,
                'isTemplate' => $plan->is_template,
                'daysCount' => $plan->workout_days_count,
                'updatedAt' => $plan->updated_at?->toISOString(),
            ]);

        return Inertia::render('WorkoutPlans::Coach/Plans/Index', [
            'plans' => $plans,
            'stats' => [
                'plans' => $plans->count(),
                'exercises' => Exercise::active()->forCoach($coach->id)->count(),
            ],
        ])->withoutSSR();
    }

    public function create(Request $request): Response
    {
        $coach = $request->user()->coach;

        return Inertia::render('WorkoutPlans::Coach/Plans/Builder', [
            'plan' => null,
            'clients' => $this->athletes($coach),
        ])->withoutSSR();
    }

    public function edit(Request $request, WorkoutPlan $plan): Response
    {
        $this->authorize('update', $plan);

        $plan->load(['workoutDays.workoutExercises.exercise']);

        return Inertia::render('WorkoutPlans::Coach/Plans/Builder', [
            'plan' => new WorkoutPlanDetailResource($plan),
            'clients' => $this->athletes($request->user()->coach),
            'assignedClientIds' => $plan->assignments()->pluck('client_id'),
        ])->withoutSSR();
    }

    public function store(StoreCoachPlanRequest $request): JsonResponse
    {
        $coach = $request->user()->coach;

        $plan = $this->writer->write($coach, null, PlanTreeData::fromArray($request->validated()));
        $this->maybeAssign($request, $plan);

        return response()->json(['plan' => new WorkoutPlanDetailResource($plan)], 201);
    }

    public function update(UpdateCoachPlanRequest $request, WorkoutPlan $plan): JsonResponse
    {
        $this->authorize('update', $plan);

        $plan = $this->writer->write($request->user()->coach, $plan, PlanTreeData::fromArray($request->validated()));
        $this->maybeAssign($request, $plan);

        return response()->json(['plan' => new WorkoutPlanDetailResource($plan)]);
    }

    public function duplicate(Request $request, WorkoutPlan $plan): RedirectResponse
    {
        $this->authorize('duplicate', $plan);

        $copy = $plan->replicate();
        $copy->name = $plan->name.' (copia)';
        $copy->save();

        foreach ($plan->workoutDays()->with('workoutExercises')->get() as $day) {
            $newDay = $day->replicate(['workout_plan_id']);
            $newDay->workout_plan_id = $copy->id;
            $newDay->save();

            foreach ($day->workoutExercises as $exercise) {
                $newExercise = $exercise->replicate(['workout_plan_id', 'workout_day_id']);
                $newExercise->workout_plan_id = $copy->id;
                $newExercise->workout_day_id = $newDay->id;
                $newExercise->save();
            }
        }

        return redirect()->route('coach.plans.edit', $copy);
    }

    public function destroy(Request $request, WorkoutPlan $plan): RedirectResponse
    {
        $this->authorize('delete', $plan);

        $plan->delete();

        return redirect()->route('coach.plans.index');
    }

    /** Assignments change only when the client explicitly sends clientIds. */
    private function maybeAssign(Request $request, WorkoutPlan $plan): void
    {
        if (! $request->has('clientIds')) {
            return;
        }

        $activeFrom = $request->input('activeFrom') ? Carbon::parse($request->input('activeFrom')) : null;

        $this->writer->syncAssignments($plan, $request->input('clientIds', []), $activeFrom);
    }

    /** @return Collection<int, array{id: int, name: string}> */
    private function athletes(Coach $coach): Collection
    {
        return $coach->athletes()->with('user')->get()
            ->map(fn (Client $client): array => [
                'id' => $client->id,
                'name' => $client->user?->name ?? '',
            ]);
    }
}
```

`edit()` uses `$plan->assignments()`. That relation does not exist on `WorkoutPlan` yet. Add it to `modules/WorkoutPlans/app/Models/WorkoutPlan.php`:

```php
    /** @return HasMany<PlanAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(PlanAssignment::class);
    }
```

`Exercise::forCoach()` already exists as a scope and returns global + this coach's exercises.

- [ ] **Step 5: Wire the routes**

Replace `modules/WorkoutPlans/routes/web.php` entirely:

```php
<?php

use Illuminate\Support\Facades\Route;
use Modules\WorkoutPlans\Http\Controllers\Coach\ExerciseController;
use Modules\WorkoutPlans\Http\Controllers\Coach\PlanBuilderController;

Route::middleware(['auth', 'role:coach'])->prefix('coach')->name('coach.')->group(function () {
    Route::get('plans', [PlanBuilderController::class, 'index'])->name('plans.index');
    Route::get('plans/create', [PlanBuilderController::class, 'create'])->name('plans.create');
    Route::post('plans', [PlanBuilderController::class, 'store'])->name('plans.store');
    Route::get('plans/{plan}/edit', [PlanBuilderController::class, 'edit'])->name('plans.edit');
    Route::put('plans/{plan}', [PlanBuilderController::class, 'update'])->name('plans.update');
    Route::post('plans/{plan}/duplicate', [PlanBuilderController::class, 'duplicate'])->name('plans.duplicate');
    Route::delete('plans/{plan}', [PlanBuilderController::class, 'destroy'])->name('plans.destroy');

    Route::get('exercises', [ExerciseController::class, 'index'])->name('exercises.index');
    Route::post('exercises', [ExerciseController::class, 'store'])->name('exercises.store');
    Route::put('exercises/{exercise}', [ExerciseController::class, 'update'])->name('exercises.update');
    Route::delete('exercises/{exercise}', [ExerciseController::class, 'destroy'])->name('exercises.destroy');
});
```

The `exercises` routes point at Task 9's controller. Comment them out until that task lands, or implement Task 9 first — either way `php artisan route:list` must not error.

Update `modules/WorkoutPlans/routes/navigation.php` — change `route('workoutplans.index')` to `route('coach.plans.index')`.

Delete `modules/WorkoutPlans/app/Http/Controllers/WorkoutPlansController.php` and `modules/WorkoutPlans/resources/js/pages/Index.vue`. Task 12 recreates the page at its new path. Delete the old E2E test `modules/WorkoutPlans/tests/e2e/index.spec.ts`, which only asserted that `/` responds.

- [ ] **Step 6: Run the test to verify it passes**

First, find out what Spatie's `role` middleware does to an unauthorized user in this app — it can either throw `UnauthorizedException` (403) or redirect, depending on the exception handler:

Run: `php artisan tinker --execute 'echo get_class(app(Illuminate\Contracts\Debug\ExceptionHandler::class));'`

Then check how `modules/Users` tests assert it. Grep for an existing coach-route test:

Run: `grep -rn "assertForbidden\|assertRedirect" modules/Users/tests/Feature | head`

Match that expectation in `test_a_client_cannot_reach_the_builder`. Do not change the middleware to satisfy the test.

`assertInertia` inspects the response payload, not the Vue file, so the component assertions pass before Task 12 creates `Coach/Plans/Index.vue`.

Run: `php -d memory_limit=2048M artisan test --compact --filter=PlanBuilderControllerTest`

Expected: PASS, 11 tests.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add modules/WorkoutPlans/app/Http/Controllers/Coach/PlanBuilderController.php \
        modules/WorkoutPlans/app/Http/Requests/StoreCoachPlanRequest.php \
        modules/WorkoutPlans/app/Http/Requests/UpdateCoachPlanRequest.php \
        modules/WorkoutPlans/app/Models/WorkoutPlan.php \
        modules/WorkoutPlans/routes modules/WorkoutPlans/tests/Feature/Coach
git rm modules/WorkoutPlans/app/Http/Controllers/WorkoutPlansController.php \
       modules/WorkoutPlans/resources/js/pages/Index.vue \
       modules/WorkoutPlans/tests/e2e/index.spec.ts
git commit -m "feat(workoutplans): add coach web routes and plan builder controller"
```

---

### Task 9: Coach exercise controller

Web CRUD for custom exercises, with the delete-in-use guard the new foreign key demands.

**Files:**
- Create: `modules/WorkoutPlans/app/Http/Controllers/Coach/ExerciseController.php`
- Create: `modules/WorkoutPlans/app/Http/Requests/StoreCoachExerciseRequest.php`
- Create: `modules/WorkoutPlans/app/Http/Requests/UpdateCoachExerciseRequest.php`
- Modify: `modules/WorkoutPlans/app/Http/Controllers/Api/V1/ExerciseController.php`
- Test: `modules/WorkoutPlans/tests/Feature/Coach/ExerciseControllerTest.php`

**Interfaces:**
- Consumes: `ExercisePolicy`, `AllowedVideoHost`, `VideoThumbnailResolver`.
- Produces: `coach.exercises.index|store|update|destroy`. `index` renders `WorkoutPlans::Coach/Exercises/Index` with an `exercises` prop shaped like `ExerciseResource`.

- [ ] **Step 1: Write the failing test**

Create `modules/WorkoutPlans/tests/Feature/Coach/ExerciseControllerTest.php`:

```php
<?php

namespace Modules\WorkoutPlans\Tests\Feature\Coach;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\WorkoutPlans\Models\Exercise;
use Modules\WorkoutPlans\Models\WorkoutDay;
use Modules\WorkoutPlans\Models\WorkoutExercise;
use Modules\WorkoutPlans\Models\WorkoutPlan;
use Tests\TestCase;

class ExerciseControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Coach $coach;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*' => Http::response(['thumbnail_url' => 'https://i.ytimg.com/vi/abc/hq.jpg'])]);

        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->user->assignRole(Role::COACH);
        $this->coach = Coach::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_the_library_lists_global_and_own_exercises_only(): void
    {
        $global = Exercise::factory()->global()->create(['name' => 'Squat']);
        $mine = Exercise::factory()->create(['coach_id' => $this->coach->id, 'name' => 'Mio']);
        Exercise::factory()->create(['coach_id' => Coach::factory()->create()->id, 'name' => 'Altrui']);

        $this->actingAs($this->user)
            ->get(route('coach.exercises.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WorkoutPlans::Coach/Exercises/Index')
                ->has('exercises', 2)
            );
    }

    public function test_creating_a_custom_exercise_resolves_a_thumbnail(): void
    {
        $this->actingAs($this->user)->postJson(route('coach.exercises.store'), [
            'name' => 'Front Squat',
            'type' => 'strength',
            'primaryMuscles' => ['legs'],
            'videoUrl' => 'https://youtu.be/abc',
        ])->assertCreated();

        $this->assertDatabaseHas('exercises', [
            'name' => 'Front Squat',
            'coach_id' => $this->coach->id,
            'thumbnail_url' => 'https://i.ytimg.com/vi/abc/hq.jpg',
        ]);
    }

    public function test_a_disallowed_video_host_is_rejected(): void
    {
        $this->actingAs($this->user)->postJson(route('coach.exercises.store'), [
            'name' => 'Sospetto',
            'videoUrl' => 'https://evil.example.com/v.mp4',
        ])->assertUnprocessable()->assertJsonValidationErrorFor('videoUrl');
    }

    public function test_a_coach_cannot_edit_a_global_exercise(): void
    {
        $global = Exercise::factory()->global()->create();

        $this->actingAs($this->user)
            ->putJson(route('coach.exercises.update', $global), ['name' => 'Rubato'])
            ->assertForbidden();
    }

    public function test_a_coach_cannot_edit_another_coachs_exercise(): void
    {
        $theirs = Exercise::factory()->create(['coach_id' => Coach::factory()->create()->id]);

        $this->actingAs($this->user)
            ->putJson(route('coach.exercises.update', $theirs), ['name' => 'Rubato'])
            ->assertForbidden();
    }

    public function test_deleting_an_unused_custom_exercise_succeeds(): void
    {
        $mine = Exercise::factory()->create(['coach_id' => $this->coach->id]);

        $this->actingAs($this->user)
            ->from(route('coach.exercises.index'))
            ->delete(route('coach.exercises.destroy', $mine))
            ->assertRedirect(route('coach.exercises.index'))
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('exercises', ['id' => $mine->id]);
    }

    public function test_deleting_a_custom_exercise_in_use_is_blocked(): void
    {
        $mine = Exercise::factory()->create(['coach_id' => $this->coach->id]);
        $plan = WorkoutPlan::factory()->create(['coach_id' => $this->coach->id]);
        $day = WorkoutDay::factory()->create(['workout_plan_id' => $plan->id]);
        WorkoutExercise::factory()->create([
            'workout_plan_id' => $plan->id,
            'workout_day_id' => $day->id,
            'exercise_id' => $mine->id,
        ]);

        $this->actingAs($this->user)
            ->from(route('coach.exercises.index'))
            ->delete(route('coach.exercises.destroy', $mine))
            ->assertRedirect(route('coach.exercises.index'))
            ->assertSessionHasErrors('exercise');

        $this->assertDatabaseHas('exercises', ['id' => $mine->id, 'deleted_at' => null]);
    }
}
```

The delete tests use `delete()` rather than `deleteJson()`, and `from()` so `back()` has somewhere to redirect to. Inertia's `router.delete` issues exactly this kind of request.

- [ ] **Step 2: Run it to make sure it fails**

Run: `php -d memory_limit=2048M artisan test --compact --filter=Coach\\ExerciseControllerTest`

Expected: FAIL with `Class "Modules\WorkoutPlans\Http\Controllers\Coach\ExerciseController" does not exist`.

- [ ] **Step 3: Write the form requests**

Create `modules/WorkoutPlans/app/Http/Requests/StoreCoachExerciseRequest.php`:

```php
<?php

namespace Modules\WorkoutPlans\Http\Requests;

use App\Rules\AllowedVideoHost;
use Illuminate\Foundation\Http\FormRequest;

class StoreCoachExerciseRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'type' => ['nullable', 'string', 'max:50'],
            'primaryMuscles' => ['nullable', 'array'],
            'primaryMuscles.*' => ['string', 'max:50'],
            'secondaryMuscles' => ['nullable', 'array'],
            'secondaryMuscles.*' => ['string', 'max:50'],
            'videoUrl' => ['nullable', 'url', 'max:500', new AllowedVideoHost],
        ];
    }
}
```

Create `modules/WorkoutPlans/app/Http/Requests/UpdateCoachExerciseRequest.php`:

```php
<?php

namespace Modules\WorkoutPlans\Http\Requests;

use App\Rules\AllowedVideoHost;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCoachExerciseRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'instructions' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'primaryMuscles' => ['sometimes', 'nullable', 'array'],
            'primaryMuscles.*' => ['string', 'max:50'],
            'secondaryMuscles' => ['sometimes', 'nullable', 'array'],
            'secondaryMuscles.*' => ['string', 'max:50'],
            'videoUrl' => ['sometimes', 'nullable', 'url', 'max:500', new AllowedVideoHost],
        ];
    }
}
```

- [ ] **Step 4: Write the controller**

Create `modules/WorkoutPlans/app/Http/Controllers/Coach/ExerciseController.php`:

```php
<?php

namespace Modules\WorkoutPlans\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\WorkoutPlans\Enums\ExerciseDifficulty;
use Modules\WorkoutPlans\Http\Requests\StoreCoachExerciseRequest;
use Modules\WorkoutPlans\Http\Requests\UpdateCoachExerciseRequest;
use Modules\WorkoutPlans\Http\Resources\ExerciseResource;
use Modules\WorkoutPlans\Models\Exercise;
use Modules\WorkoutPlans\Models\WorkoutExercise;
use Modules\WorkoutPlans\Services\VideoThumbnailResolver;

class ExerciseController extends Controller
{
    public function __construct(private VideoThumbnailResolver $thumbnails) {}

    public function index(Request $request): Response
    {
        $coach = $request->user()->coach;

        $exercises = Exercise::active()
            ->forCoach($coach->id)
            ->orderBy('name')
            ->get();

        return Inertia::render('WorkoutPlans::Coach/Exercises/Index', [
            // ->toArray() so Inertia does not wrap the collection in a `data` key.
            'exercises' => ExerciseResource::collection($exercises)->toArray($request),
        ])->withoutSSR();
    }

    public function store(StoreCoachExerciseRequest $request): JsonResponse
    {
        $exercise = Exercise::create([
            'coach_id' => $request->user()->coach->id,
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'instructions' => $request->validated('instructions'),
            'category' => $request->validated('type'),
            'difficulty' => ExerciseDifficulty::Beginner->value,
            'primary_muscles' => $request->validated('primaryMuscles', []),
            'secondary_muscles' => $request->validated('secondaryMuscles', []),
            'video_url' => $request->validated('videoUrl'),
            'thumbnail_url' => $this->thumbnails->resolve($request->validated('videoUrl')),
            'is_active' => true,
        ]);

        return response()->json(['exercise' => new ExerciseResource($exercise)], 201);
    }

    public function update(UpdateCoachExerciseRequest $request, Exercise $exercise): JsonResponse
    {
        $this->authorize('update', $exercise);

        $exercise->update(array_filter([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'instructions' => $request->validated('instructions'),
            'category' => $request->validated('type'),
            'primary_muscles' => $request->validated('primaryMuscles'),
            'secondary_muscles' => $request->validated('secondaryMuscles'),
            'video_url' => $request->validated('videoUrl'),
            'thumbnail_url' => $request->has('videoUrl')
                ? $this->thumbnails->resolve($request->validated('videoUrl'))
                : null,
        ], fn ($v) => $v !== null));

        return response()->json(['exercise' => new ExerciseResource($exercise->fresh())]);
    }

    public function destroy(Request $request, Exercise $exercise): RedirectResponse
    {
        $this->authorize('delete', $exercise);

        $plansCount = WorkoutExercise::query()
            ->where('exercise_id', $exercise->id)
            ->distinct()
            ->count('workout_plan_id');

        if ($plansCount > 0) {
            return back()->withErrors([
                'exercise' => __('This exercise is used in :count plans.', ['count' => $plansCount]),
            ]);
        }

        $exercise->delete();

        return back();
    }
}
```

Add `use Illuminate\Http\RedirectResponse;` and drop `JsonResponse` if `store`/`update` no longer need it — they do, so keep both.

Two things are deliberate here.

The usage check runs before `delete()` because `exercises` uses `SoftDeletes` — a soft delete never trips the database foreign key, so the guard has to be explicit in PHP.

And `destroy` redirects back with a validation error rather than returning `422` JSON, because this route is called from an Inertia page via `router.delete`. Inertia populates `errors` from `page.props.errors`, which a raw `JsonResponse` never reaches — the error banner would silently never appear. The mobile API's `destroy` (next step) keeps its JSON `422`, because its client reads JSON.

- [ ] **Step 5: Mirror the guard on the API**

The mobile app's `DELETE /api/v1/coaches/me/exercises/{exercise}` has the same hazard, but a JSON client. In `modules/WorkoutPlans/app/Http/Controllers/Api/V1/ExerciseController.php`, in `destroy()`, insert before `$exercise->delete();`:

```php
        $plansCount = WorkoutExercise::query()
            ->where('exercise_id', $exercise->id)
            ->distinct()
            ->count('workout_plan_id');

        if ($plansCount > 0) {
            return response()->json([
                'message' => __('This exercise is used in :count plans.', ['count' => $plansCount]),
                'plansCount' => $plansCount,
            ], 422);
        }
```

Add the `Modules\WorkoutPlans\Models\WorkoutExercise` import.

Add a test for it in `modules/WorkoutPlans/tests/Feature/Api/ExerciseControllerTest.php`:

```php
    public function test_deleting_an_exercise_in_use_returns_422(): void
    {
        $exercise = Exercise::factory()->create(['coach_id' => $this->coach->id]);
        $plan = WorkoutPlan::factory()->create(['coach_id' => $this->coach->id]);
        $day = WorkoutDay::factory()->create(['workout_plan_id' => $plan->id]);
        WorkoutExercise::factory()->create([
            'workout_plan_id' => $plan->id,
            'workout_day_id' => $day->id,
            'exercise_id' => $exercise->id,
        ]);

        $this->asCoach()
            ->deleteJson("/api/v1/coaches/me/exercises/{$exercise->id}")
            ->assertUnprocessable()
            ->assertJsonPath('plansCount', 1);
    }
```

Import whatever models that file does not already import.

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php -d memory_limit=2048M artisan test --compact --filter=ExerciseControllerTest`

Expected: PASS. Both the `Coach\` and `Api\V1\` test classes match this filter; both must be green.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add modules/WorkoutPlans/app/Http/Controllers modules/WorkoutPlans/app/Http/Requests \
        modules/WorkoutPlans/tests/Feature
git commit -m "feat(workoutplans): add coach exercise crud with delete-in-use guard"
```

---

### Task 10: Drag-and-drop dependency and the SortableJS bridge

SortableJS mutates the DOM; Vue owns the DOM. The bridge lives in exactly one file so the reconciliation bug has exactly one place to hide.

**Files:**
- Modify: `package.json`
- Create: `modules/WorkoutPlans/resources/js/composables/useSortableList.ts`

**Interfaces:**
- Consumes: nothing.
- Produces:

```typescript
useSortableList(el: Ref<HTMLElement | null>, options: SortableOptions): void

type SortableOptions = {
    group: string;
    clone?: boolean;
    handle?: string;
    onAdd?: (index: number, node: HTMLElement) => void;
    onReorder?: (from: number, to: number) => void;
    onRemove?: (index: number) => void;
};
```

  `onAdd` receives the dropped DOM node, so the receiving list can read an identifier off a data attribute rather than coordinating with the source list.

  The composable takes **no array**. It reverts SortableJS's DOM mutation and reports what happened; the caller owns the state and mutates it. Handing it an array as well would mean two mutation paths for one gesture — the day list would reorder twice, once inside the composable and once in `onReorder`. A clone-only drag source (the exercise library) has no array at all.

  Tasks 11 and 13 depend on this exact signature.

- [ ] **Step 1: Install the dependencies**

```bash
npm install sortablejs
npm install --save-dev @types/sortablejs
```

Confirm `package.json` gained `sortablejs` and `@types/sortablejs`. `@vueuse/core` is already present and must not be re-installed. Do NOT add `@vueuse/integrations` — the composable uses `Sortable.create` directly, so it would be dead weight (and it carries a major-version skew vs `@vueuse/core`).

- [ ] **Step 2: Write the bridge**

Create `modules/WorkoutPlans/resources/js/composables/useSortableList.ts`:

```typescript
import Sortable from 'sortablejs';
import { onBeforeUnmount, watch, type Ref } from 'vue';

type SortableOptions = {
    /** Lists sharing a group name can exchange items. */
    group: string;
    /** Source lists clone on drag out instead of losing the item. */
    clone?: boolean;
    /** CSS selector for the drag handle. Omit to drag by the whole element. */
    handle?: string;
    /**
     * An item was dropped into this list from another one.
     * `node` is the DOM element SortableJS inserted — read a data attribute
     * off it to identify what was dragged, rather than reaching into the
     * source list's state.
     */
    onAdd?: (index: number, node: HTMLElement) => void;
    /** Called when an item is reordered within this list. */
    onReorder?: (from: number, to: number) => void;
    /** Called when an item is dragged out of this list into another. */
    onRemove?: (index: number) => void;
};

/**
 * Bridge SortableJS to Vue-owned state.
 *
 * SortableJS moves DOM nodes directly. Vue then re-renders from its own state,
 * finds nodes it did not put there, and the card duplicates or vanishes. The
 * fix is to undo every DOM mutation SortableJS makes, then report the gesture
 * to the caller, who mutates the state Vue renders from.
 *
 * This composable never touches an array. One gesture, one mutation, in the
 * caller.
 */
export function useSortableList(
    el: Ref<HTMLElement | null>,
    options: SortableOptions,
): void {
    let sortable: Sortable | null = null;

    // Watch the ref rather than binding onMounted: the day list lives inside a
    // v-if and is destroyed and recreated whenever the coach switches tabs.
    watch(el, (element) => {
        sortable?.destroy();
        sortable = null;

        if (!element) {
            return;
        }

        sortable = Sortable.create(element, {
            group: options.clone
                ? { name: options.group, pull: 'clone', put: false }
                : { name: options.group, pull: true, put: true },
            handle: options.handle,
            animation: 150,
            ghostClass: 'opacity-40',

            onEnd(event) {
                if (event.from !== event.to) {
                    return; // handled by onAdd / onRemove
                }

                const { oldIndex, newIndex } = event;

                if (oldIndex === undefined || newIndex === undefined || oldIndex === newIndex) {
                    return;
                }

                // Undo SortableJS's DOM move; Vue's state is the source of truth.
                const parent = event.from;
                const moved = parent.children[newIndex];
                parent.removeChild(moved);
                parent.insertBefore(moved, parent.children[oldIndex] ?? null);

                options.onReorder?.(oldIndex, newIndex);
            },

            onAdd(event) {
                const { newIndex, item } = event;

                if (newIndex === undefined) {
                    return;
                }

                // Remove the node SortableJS inserted (a clone, or the original)
                // BEFORE mutating the array, or Vue renders a duplicate.
                item.remove();

                options.onAdd?.(newIndex, item);
            },

            onRemove(event) {
                if (options.clone) {
                    return; // the source list keeps its item
                }

                const { oldIndex } = event;

                if (oldIndex === undefined) {
                    return;
                }

                options.onRemove?.(oldIndex);
            },
        });
    }, { immediate: true, flush: 'post' });

    onBeforeUnmount(() => {
        sortable?.destroy();
        sortable = null;
    });
}
```

`flush: 'post'` makes the watcher run after Vue has patched the DOM, so `el.value` is the element that is actually on the page.

Each handler reverts the DOM before touching the array. That is the whole trick: SortableJS thinks it moved a node, we put it back, and Vue's next render places it where the array says it goes. Without the revert, Vue's patch and SortableJS's move compound into a duplicate.

- [ ] **Step 3: Verify it type-checks**

Run: `npx vue-tsc --noEmit -p tsconfig.json`

Expected: no errors mentioning `useSortableList.ts`. If `Sortable` has no default export in your version, change the import to `import Sortable from 'sortablejs/modular/sortable.core.esm.js';` and re-run.

- [ ] **Step 4: Commit**

```bash
npm run lint
git add package.json package-lock.json modules/WorkoutPlans/resources/js/composables/useSortableList.ts
git commit -m "build: add sortablejs and a reactive-array bridge composable"
```

---

### Task 11: Exercise library components

One `ExerciseLibrary` serves both the builder's left column and the standalone page. Two variants, one search implementation.

**Files:**
- Create: `modules/WorkoutPlans/resources/js/components/ExerciseCard.vue`
- Create: `modules/WorkoutPlans/resources/js/components/VideoPreviewModal.vue`
- Create: `modules/WorkoutPlans/resources/js/components/ExerciseLibrary.vue`
- Create: `modules/WorkoutPlans/resources/js/types/exercises.ts`

**Interfaces:**
- Consumes: `useSortableList` (Task 10), generated enums from `types/generated.d.ts`.
- Produces:
  - `types/exercises.ts` exporting `ExerciseListItem` — mirrors `ExerciseResource`: `{ id, name, description, instructions, category, difficulty, primaryMuscles, secondaryMuscles, muscleGroups, equipment, videoUrl, thumbnailUrl, isCustom, coachId, isActive, createdAt }`.
  - `<ExerciseLibrary :exercises variant="sidebar"|"grid" @add="(e: ExerciseListItem) => void" @edit @delete />`
  - `<ExerciseCard :exercise :variant @add @preview @edit @delete />`
  - `<VideoPreviewModal :url :open @close />`

  Tasks 13 and 14 consume these exact prop and event names.

- [ ] **Step 1: Define the shared type**

Create `modules/WorkoutPlans/resources/js/types/exercises.ts`:

```typescript
export type ExerciseListItem = {
    id: number;
    name: string;
    description: string | null;
    instructions: string | null;
    category: Modules.WorkoutPlans.Enums.ExerciseCategory | null;
    difficulty: Modules.WorkoutPlans.Enums.ExerciseDifficulty | null;
    primaryMuscles: string[];
    secondaryMuscles: string[];
    muscleGroups: string[];
    equipment: string[];
    videoUrl: string | null;
    thumbnailUrl: string | null;
    isCustom: boolean;
    coachId: number | null;
    isActive: boolean;
    createdAt: string | null;
};
```

- [ ] **Step 2: Write `VideoPreviewModal.vue`**

```vue
<script setup lang="ts">
import { computed } from 'vue';
import Dialog from '@/components/ui/dialog/Dialog.vue';
import DialogContent from '@/components/ui/dialog/DialogContent.vue';

const props = defineProps<{
    url: string | null;
    open: boolean;
}>();

defineEmits<{ close: [] }>();

/**
 * Build a provider embed URL. The stored url already passed the
 * AllowedVideoHost rule server-side, so only YouTube and Vimeo reach here.
 */
const embedUrl = computed(() => {
    if (!props.url) {
        return null;
    }

    const parsed = new URL(props.url);

    if (parsed.hostname === 'youtu.be') {
        return `https://www.youtube.com/embed${parsed.pathname}`;
    }

    if (parsed.hostname.endsWith('youtube.com')) {
        const id = parsed.searchParams.get('v');
        return id ? `https://www.youtube.com/embed/${id}` : null;
    }

    if (parsed.hostname.endsWith('vimeo.com')) {
        const id = parsed.pathname.split('/').filter(Boolean).pop();
        return id ? `https://player.vimeo.com/video/${id}` : null;
    }

    return null;
});
</script>

<template>
    <Dialog :open="open" @update:open="$emit('close')">
        <DialogContent
            class="max-w-3xl bg-white dark:bg-gray-900"
            data-testid="video-preview-modal"
        >
            <div v-if="embedUrl" class="aspect-video w-full">
                <iframe
                    :src="embedUrl"
                    class="h-full w-full rounded-lg"
                    allowfullscreen
                    data-testid="video-preview-iframe"
                />
            </div>
            <p
                v-else
                class="py-12 text-center text-gray-600 dark:text-gray-400"
                data-testid="video-preview-empty"
            >
                {{ $t('No video available') }}
            </p>
        </DialogContent>
    </Dialog>
</template>
```

If `@/components/ui/dialog` does not exist in this project, check `resources/js/components/ui/` and use whatever modal primitive is there (`Sheet`, `AlertDialog`). Do not add a new UI dependency.

- [ ] **Step 3: Write `ExerciseCard.vue`**

```vue
<script setup lang="ts">
import { ref } from 'vue';
import Badge from '@/components/ui/badge/Badge.vue';
import Button from '@/components/ui/button/Button.vue';
import IconEllipsis from '~icons/heroicons/ellipsis-vertical';
import IconPlay from '~icons/heroicons/play-circle';
import IconPlus from '~icons/heroicons/plus';
import type { ExerciseListItem } from '../types/exercises';

defineProps<{
    exercise: ExerciseListItem;
    variant: 'sidebar' | 'grid';
}>();

const emit = defineEmits<{
    add: [exercise: ExerciseListItem];
    preview: [exercise: ExerciseListItem];
    edit: [exercise: ExerciseListItem];
    remove: [exercise: ExerciseListItem];
}>();

const menuOpen = ref(false);
</script>

<template>
    <div
        class="group relative flex gap-3 rounded-lg border border-gray-200 bg-white p-3 transition hover:border-gray-300 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-gray-700"
        :class="variant === 'grid' ? 'flex-col' : 'flex-row items-center'"
        :data-testid="`exercise-card-${exercise.id}`"
        :data-exercise-id="exercise.id"
    >
        <button
            type="button"
            class="relative shrink-0 overflow-hidden rounded-md bg-gray-100 dark:bg-gray-800"
            :class="variant === 'grid' ? 'aspect-video w-full' : 'h-12 w-12'"
            :data-testid="`preview-exercise-${exercise.id}`"
            @click="emit('preview', exercise)"
        >
            <img
                v-if="exercise.thumbnailUrl"
                :src="exercise.thumbnailUrl"
                :alt="exercise.name"
                class="h-full w-full object-cover"
            />
            <IconPlay
                v-else
                class="absolute inset-0 m-auto h-6 w-6 text-gray-400 dark:text-gray-500"
            />
        </button>

        <div class="min-w-0 flex-1">
            <p class="truncate font-medium text-gray-900 dark:text-white">
                {{ exercise.name }}
            </p>
            <div class="mt-1 flex flex-wrap gap-1">
                <Badge v-for="muscle in exercise.primaryMuscles" :key="muscle" variant="secondary">
                    {{ muscle }}
                </Badge>
                <Badge v-if="exercise.isCustom" variant="outline">{{ $t('Custom') }}</Badge>
            </div>
        </div>

        <Button
            v-if="variant === 'sidebar'"
            size="icon"
            variant="ghost"
            :data-testid="`add-exercise-${exercise.id}`"
            :aria-label="$t('Add to day')"
            @click="emit('add', exercise)"
        >
            <IconPlus class="h-4 w-4" />
        </Button>

        <div v-if="exercise.isCustom" class="relative">
            <Button
                size="icon"
                variant="ghost"
                :data-testid="`exercise-actions-${exercise.id}`"
                @click="menuOpen = !menuOpen"
            >
                <IconEllipsis class="h-4 w-4" />
            </Button>
            <div
                v-if="menuOpen"
                class="absolute right-0 z-10 mt-1 w-36 rounded-md border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-800 dark:bg-gray-900"
            >
                <button
                    type="button"
                    class="block w-full px-3 py-1.5 text-left text-sm text-gray-900 hover:bg-gray-50 dark:text-white dark:hover:bg-gray-800"
                    :data-testid="`edit-exercise-${exercise.id}`"
                    @click="menuOpen = false; emit('edit', exercise)"
                >
                    {{ $t('Edit') }}
                </button>
                <button
                    type="button"
                    class="block w-full px-3 py-1.5 text-left text-sm text-red-600 hover:bg-gray-50 dark:text-red-400 dark:hover:bg-gray-800"
                    :data-testid="`delete-exercise-${exercise.id}`"
                    @click="menuOpen = false; emit('remove', exercise)"
                >
                    {{ $t('Delete') }}
                </button>
            </div>
        </div>
    </div>
</template>
```

Two details carry weight here. The `+` button has an `aria-label` because its only content is an icon, and it renders only in the sidebar variant — on the standalone library page there is no day to add to. And the root element carries `data-exercise-id`: when a card is dragged into a day, SortableJS hands the receiving list that DOM node, and reading the id off the node is how the day learns which exercise arrived. No shared state between the two lists.

- [ ] **Step 4: Write `ExerciseLibrary.vue`**

```vue
<script setup lang="ts">
import { computed, ref } from 'vue';
import Input from '@/components/ui/input/Input.vue';
import IconSearch from '~icons/heroicons/magnifying-glass';
import ExerciseCard from './ExerciseCard.vue';
import VideoPreviewModal from './VideoPreviewModal.vue';
import { useSortableList } from '../composables/useSortableList';
import type { ExerciseListItem } from '../types/exercises';

const props = defineProps<{
    exercises: ExerciseListItem[];
    variant: 'sidebar' | 'grid';
}>();

const emit = defineEmits<{
    add: [exercise: ExerciseListItem];
    edit: [exercise: ExerciseListItem];
    remove: [exercise: ExerciseListItem];
}>();

const search = ref('');
const typeFilter = ref('');
const muscleFilter = ref('');
const previewing = ref<ExerciseListItem | null>(null);

const types = computed(() =>
    [...new Set(props.exercises.map((e) => e.category).filter(Boolean))].sort(),
);

const muscles = computed(() =>
    [...new Set(props.exercises.flatMap((e) => e.primaryMuscles))].sort(),
);

const filtered = computed(() => {
    const query = search.value.trim().toLowerCase();

    return props.exercises.filter((exercise) => {
        if (query && !exercise.name.toLowerCase().includes(query)) {
            return false;
        }
        if (typeFilter.value && exercise.category !== typeFilter.value) {
            return false;
        }
        if (muscleFilter.value && !exercise.primaryMuscles.includes(muscleFilter.value)) {
            return false;
        }
        return true;
    });
});

// The library is a drag SOURCE only: cards clone out, nothing drops in.
// Binding it here rather than in the parent keeps the filtered list and the
// sortable instance in the same component.
const listEl = ref<HTMLElement | null>(null);

if (props.variant === 'sidebar') {
    useSortableList(listEl, { group: 'exercises', clone: true });
}
</script>

<template>
    <div class="flex h-full flex-col gap-3">
        <div class="relative">
            <IconSearch
                class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500"
            />
            <Input
                v-model="search"
                :placeholder="$t('Search exercises')"
                class="pl-9"
                data-testid="library-search"
            />
        </div>

        <div class="flex gap-2">
            <select
                v-model="typeFilter"
                class="flex-1 rounded-md border border-gray-200 bg-white px-2 py-1.5 text-sm text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                data-testid="library-filter-type"
            >
                <option value="">{{ $t('All types') }}</option>
                <option v-for="type in types" :key="type!" :value="type">{{ type }}</option>
            </select>
            <select
                v-model="muscleFilter"
                class="flex-1 rounded-md border border-gray-200 bg-white px-2 py-1.5 text-sm text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                data-testid="library-filter-muscle"
            >
                <option value="">{{ $t('All muscles') }}</option>
                <option v-for="muscle in muscles" :key="muscle" :value="muscle">{{ muscle }}</option>
            </select>
        </div>

        <div
            ref="listEl"
            class="min-h-0 flex-1 overflow-y-auto"
            :class="variant === 'grid' ? 'grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3' : 'flex flex-col gap-2'"
            data-testid="library-list"
        >
            <ExerciseCard
                v-for="exercise in filtered"
                :key="exercise.id"
                :exercise="exercise"
                :variant="variant"
                @add="emit('add', $event)"
                @edit="emit('edit', $event)"
                @remove="emit('remove', $event)"
                @preview="previewing = $event"
            />

            <p
                v-if="filtered.length === 0"
                class="col-span-full py-12 text-center text-gray-600 dark:text-gray-400"
                data-testid="library-empty"
            >
                {{ $t('No exercises match your filters.') }}
            </p>
        </div>

        <VideoPreviewModal
            :url="previewing?.videoUrl ?? null"
            :open="previewing !== null"
            @close="previewing = null"
        />
    </div>
</template>
```

The library passes no callbacks at all: a clone-only source never reorders and never loses items. The identity of the dragged card travels on the DOM node's `data-exercise-id`, which the receiving day list reads in Task 13.

- [ ] **Step 5: Verify it builds and type-checks**

Run: `npx vue-tsc --noEmit -p tsconfig.json && npm run build`

Expected: no errors. A missing `Dialog` component surfaces here.

- [ ] **Step 6: Commit**

```bash
npm run lint && npm run format
git add modules/WorkoutPlans/resources/js/components modules/WorkoutPlans/resources/js/types/exercises.ts
git commit -m "feat(workoutplans): add shared exercise library components"
```

---

### Task 12: Plan list page

The landing page, moved to the coach namespace and given the plan table it always should have had.

**Files:**
- Create: `modules/WorkoutPlans/resources/js/pages/Coach/Plans/Index.vue`
- Test: covered by `PlanBuilderControllerTest::test_coach_sees_the_plan_list` (Task 8) plus the E2E in Task 15.

**Interfaces:**
- Consumes: the `plans` and `stats` props from `PlanBuilderController@index`.
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Write the page**

Create `modules/WorkoutPlans/resources/js/pages/Coach/Plans/Index.vue`:

```vue
<script setup lang="ts">
import Badge from '@/components/ui/badge/Badge.vue';
import Button from '@/components/ui/button/Button.vue';
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import CoachLayout from '@/layouts/CoachLayout.vue';
import { Link } from '@inertiajs/vue3';

import IconClipboardDocumentList from '~icons/heroicons/clipboard-document-list';
import IconPlus from '~icons/heroicons/plus';
import IconRectangleStack from '~icons/heroicons/rectangle-stack';

type PlanListItem = {
    id: number;
    name: string;
    goal: string | null;
    isTemplate: boolean;
    daysCount: number;
    updatedAt: string | null;
};

defineProps<{
    plans: PlanListItem[];
    stats: { plans: number; exercises: number };
}>();

const title = 'Workout Plans';

const formatDate = (iso: string | null): string =>
    iso ? new Date(iso).toLocaleDateString('it-IT', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
</script>

<template>
    <CoachLayout :title="title" :breadcrumbs="[{ title }]">
        <div class="flex flex-1 flex-col gap-6 p-6 pt-2">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                        {{ $t('Workout Plans') }}
                    </h1>
                    <p class="mt-1 text-gray-600 dark:text-gray-400">
                        {{ $t('Build and assign training plans to your athletes.') }}
                    </p>
                </div>
                <Link :href="route('coach.plans.create')">
                    <Button data-testid="new-plan-btn">
                        <IconPlus class="mr-2 h-4 w-4" />
                        {{ $t('New plan') }}
                    </Button>
                </Link>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <Card data-testid="stat-plans">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium">{{ $t('Plans') }}</CardTitle>
                        <IconClipboardDocumentList class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ stats.plans }}</p>
                    </CardContent>
                </Card>
                <Card data-testid="stat-exercises">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium">{{ $t('Exercises') }}</CardTitle>
                        <IconRectangleStack class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ stats.exercises }}</p>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardContent class="p-0">
                    <table class="w-full text-sm" data-testid="plans-table">
                        <thead class="border-b border-gray-200 dark:border-gray-800">
                            <tr class="text-left text-gray-600 dark:text-gray-400">
                                <th class="px-4 py-3 font-medium">{{ $t('Name') }}</th>
                                <th class="hidden px-4 py-3 font-medium md:table-cell">{{ $t('Goal') }}</th>
                                <th class="hidden px-4 py-3 font-medium md:table-cell">{{ $t('Days') }}</th>
                                <th class="hidden px-4 py-3 font-medium lg:table-cell">{{ $t('Updated') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="plan in plans"
                                :key="plan.id"
                                class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50"
                                :data-testid="`plan-row-${plan.id}`"
                            >
                                <td class="px-4 py-3">
                                    <Link
                                        :href="route('coach.plans.edit', plan.id)"
                                        class="font-medium text-primary/70 underline-offset-4 hover:underline"
                                        :data-testid="`open-plan-${plan.id}`"
                                    >
                                        {{ plan.name }}
                                    </Link>
                                    <Badge v-if="plan.isTemplate" variant="outline" class="ml-2">
                                        {{ $t('Template') }}
                                    </Badge>
                                </td>
                                <td class="hidden px-4 py-3 text-gray-600 md:table-cell dark:text-gray-400">
                                    {{ plan.goal ?? '—' }}
                                </td>
                                <td class="hidden px-4 py-3 text-gray-600 md:table-cell dark:text-gray-400">
                                    {{ plan.daysCount }}
                                </td>
                                <td class="hidden px-4 py-3 text-gray-600 lg:table-cell dark:text-gray-400">
                                    {{ formatDate(plan.updatedAt) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p
                        v-if="plans.length === 0"
                        class="py-16 text-center text-gray-600 dark:text-gray-400"
                        data-testid="plans-empty"
                    >
                        {{ $t('No plans yet. Create your first one.') }}
                    </p>
                </CardContent>
            </Card>
        </div>
    </CoachLayout>
</template>
```

- [ ] **Step 2: Verify the page renders**

Run: `php -d memory_limit=2048M artisan test --compact --filter=test_coach_sees_the_plan_list`

Expected: PASS.

Run: `npm run build`

Expected: no errors.

- [ ] **Step 3: Commit**

```bash
npm run lint && npm run format
git add modules/WorkoutPlans/resources/js/pages/Coach/Plans/Index.vue
git commit -m "feat(workoutplans): add coach plan list page"
```

---

### Task 13: The builder

Three columns, drag and drop, inline edit, debounced auto-save with retry.

**Files:**
- Create: `modules/WorkoutPlans/resources/js/composables/usePlanBuilder.ts`
- Create: `modules/WorkoutPlans/resources/js/components/BuilderToolbar.vue`
- Create: `modules/WorkoutPlans/resources/js/components/DayTabs.vue`
- Create: `modules/WorkoutPlans/resources/js/components/PlanExerciseCard.vue`
- Create: `modules/WorkoutPlans/resources/js/components/PlanSettingsPanel.vue`
- Create: `modules/WorkoutPlans/resources/js/pages/Coach/Plans/Builder.vue`

**Interfaces:**
- Consumes: `useSortableList` (Task 10), `ExerciseLibrary` (Task 11), `coach.plans.store|update|duplicate` (Task 8).
- Produces: nothing consumed by later PHP tasks. Task 15's E2E depends on the `data-testid` values below.

- [ ] **Step 1: Write the composable**

Create `modules/WorkoutPlans/resources/js/composables/usePlanBuilder.ts`:

```typescript
import { computed, reactive, ref, watch } from 'vue';
import type { ExerciseListItem } from '../types/exercises';

export type BuilderExercise = {
    id: number | null;
    exerciseId: number;
    name: string;
    sets: number | null;
    repsTarget: number | null;
    weightKg: number | null;
    rirTarget: number | null;
    restSeconds: number | null;
    notes: string | null;
};

export type BuilderDay = {
    id: number | null;
    dayNumber: number;
    name: string | null;
    exercises: BuilderExercise[];
};

export type BuilderPlan = {
    name: string;
    description: string | null;
    goal: string | null;
    difficulty: string | null;
    durationWeeks: number | null;
    sessionsPerWeek: number | null;
    isTemplate: boolean;
    days: BuilderDay[];
};

type SaveState = 'idle' | 'saving' | 'error';

const AUTOSAVE_DELAY_MS = 30_000;
const RETRY_DELAYS_MS = [2_000, 8_000, 30_000];

/** The shape PlanBuilderController@edit sends: a WorkoutPlanDetailResource. */
type PlanResource = {
    id: number;
    name: string;
    description: string | null;
    goal: string | null;
    difficulty: string | null;
    durationWeeks: number | null;
    sessionsPerWeek: number | null;
    isTemplate: boolean;
    days: Array<{
        id: number;
        dayNumber: number;
        name: string | null;
        exercises: Array<{
            id: number;
            exerciseId: number;
            exercise: { name: string };
            sets: number | null;
            repsTarget: number | null;
            weightKg: number | null;
            rirTarget: number | null;
            restSeconds: number | null;
            notes: string | null;
        }>;
    }>;
};

export const EMPTY_PLAN: BuilderPlan = {
    name: '',
    description: null,
    goal: null,
    difficulty: null,
    durationWeeks: null,
    sessionsPerWeek: null,
    isTemplate: false,
    days: [],
};

/** Flatten the server resource into the flat shape the builder edits. */
export function toBuilderPlan(resource: PlanResource | null): BuilderPlan {
    if (resource === null) {
        return structuredClone(EMPTY_PLAN);
    }

    return {
        name: resource.name,
        description: resource.description,
        goal: resource.goal,
        difficulty: resource.difficulty,
        durationWeeks: resource.durationWeeks,
        sessionsPerWeek: resource.sessionsPerWeek,
        isTemplate: resource.isTemplate,
        days: resource.days.map((day) => ({
            id: day.id,
            dayNumber: day.dayNumber,
            name: day.name,
            exercises: day.exercises.map((exercise) => ({
                id: exercise.id,
                exerciseId: exercise.exerciseId,
                name: exercise.exercise.name,
                sets: exercise.sets,
                repsTarget: exercise.repsTarget,
                weightKg: exercise.weightKg,
                rirTarget: exercise.rirTarget,
                restSeconds: exercise.restSeconds,
                notes: exercise.notes,
            })),
        })),
    };
}

export function usePlanBuilder(initialPlan: BuilderPlan, initialId: number | null) {
    const plan = reactive<BuilderPlan>(structuredClone(initialPlan));
    const planId = ref<number | null>(initialId);
    const clientIds = ref<number[]>([]);
    const isDirty = ref(false);
    const lastSavedAt = ref<Date | null>(null);
    const saveState = ref<SaveState>('idle');
    const isGone = ref(false);
    const activeDayIndex = ref(0);

    let debounceTimer: ReturnType<typeof setTimeout> | null = null;
    let retryTimer: ReturnType<typeof setTimeout> | null = null;
    let inFlight = false;
    let savePending = false;

    const activeDay = computed<BuilderDay | undefined>(() => plan.days[activeDayIndex.value]);

    /** A plan is worth persisting once it has a name or any exercise. */
    const hasSomethingToSave = computed(
        () => plan.name.trim() !== '' || plan.days.some((day) => day.exercises.length > 0),
    );

    function payload(includeClientIds: boolean): Record<string, unknown> {
        const body: Record<string, unknown> = {
            name: plan.name,
            description: plan.description,
            goal: plan.goal,
            difficulty: plan.difficulty,
            durationWeeks: plan.durationWeeks,
            sessionsPerWeek: plan.sessionsPerWeek,
            isTemplate: plan.isTemplate,
            days: plan.days.map((day) => ({
                id: day.id,
                dayNumber: day.dayNumber,
                name: day.name,
                exercises: day.exercises.map((exercise) => ({
                    id: exercise.id,
                    exerciseId: exercise.exerciseId,
                    sets: exercise.sets,
                    repsTarget: exercise.repsTarget,
                    weightKg: exercise.weightKg,
                    rirTarget: exercise.rirTarget,
                    restSeconds: exercise.restSeconds,
                    notes: exercise.notes,
                })),
            })),
        };

        // Auto-save must never touch assignments.
        if (includeClientIds) {
            body.clientIds = clientIds.value;
        }

        return body;
    }

    /** Copy server-assigned ids back into local state so the next save upserts. */
    function absorbIds(saved: { id: number; days?: Array<{ id: number; exercises?: Array<{ id: number }> }> }): void {
        planId.value = saved.id;

        saved.days?.forEach((savedDay, dayIndex) => {
            const day = plan.days[dayIndex];

            if (!day) {
                return;
            }

            day.id = savedDay.id;

            savedDay.exercises?.forEach((savedExercise, exerciseIndex) => {
                const exercise = day.exercises[exerciseIndex];

                if (exercise) {
                    exercise.id = savedExercise.id;
                }
            });
        });
    }

    async function save({ withAssignments = false, attempt = 0 } = {}): Promise<void> {
        if (isGone.value || !hasSomethingToSave.value) {
            return;
        }

        if (inFlight) {
            savePending = true;
            return;
        }

        inFlight = true;
        saveState.value = 'saving';

        const creating = planId.value === null;
        const url = creating ? route('coach.plans.store') : route('coach.plans.update', planId.value!);

        try {
            const response = await fetch(url, {
                method: creating ? 'POST' : 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': decodeURIComponent(
                        document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                    ),
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload(withAssignments)),
            });

            if (response.status === 404) {
                isGone.value = true;
                saveState.value = 'error';
                return;
            }

            if (!response.ok) {
                throw new Error(`save failed: ${response.status}`);
            }

            const body = await response.json();
            absorbIds(body.plan);

            saveState.value = 'idle';
            isDirty.value = false;
            lastSavedAt.value = new Date();
        } catch (error) {
            saveState.value = 'error';

            if (attempt < RETRY_DELAYS_MS.length) {
                retryTimer = setTimeout(
                    () => void save({ withAssignments, attempt: attempt + 1 }),
                    RETRY_DELAYS_MS[attempt],
                );
            }
        } finally {
            inFlight = false;

            if (savePending) {
                savePending = false;
                void save({ withAssignments });
            }
        }
    }

    /** Called on blur of an inline field: persist immediately, don't wait 30s. */
    function saveNow(): void {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
            debounceTimer = null;
        }

        void save();
    }

    function saveAndAssign(): Promise<void> {
        return save({ withAssignments: true });
    }

    /** The plan was deleted elsewhere; re-create it under a new id. */
    function saveAsNew(): Promise<void> {
        isGone.value = false;
        planId.value = null;

        plan.days.forEach((day) => {
            day.id = null;
            day.exercises.forEach((exercise) => {
                exercise.id = null;
            });
        });

        return save();
    }

    function addDay(): void {
        const dayNumber = plan.days.length + 1;
        plan.days.push({ id: null, dayNumber, name: null, exercises: [] });
        activeDayIndex.value = plan.days.length - 1;
    }

    function removeDay(index: number): void {
        plan.days.splice(index, 1);
        plan.days.forEach((day, position) => {
            day.dayNumber = position + 1;
        });
        activeDayIndex.value = Math.max(0, Math.min(activeDayIndex.value, plan.days.length - 1));
    }

    function addExercise(source: ExerciseListItem, atIndex?: number): void {
        if (plan.days.length === 0) {
            addDay();
        }

        const day = plan.days[activeDayIndex.value];

        const item: BuilderExercise = {
            id: null,
            exerciseId: source.id,
            name: source.name,
            sets: 3,
            repsTarget: 10,
            weightKg: null,
            rirTarget: 2,
            restSeconds: 90,
            notes: null,
        };

        if (atIndex === undefined) {
            day.exercises.push(item);
        } else {
            day.exercises.splice(atIndex, 0, item);
        }
    }

    function removeExercise(dayIndex: number, exerciseIndex: number): void {
        plan.days[dayIndex].exercises.splice(exerciseIndex, 1);
    }

    function moveExercise(dayIndex: number, from: number, to: number): void {
        const exercises = plan.days[dayIndex].exercises;

        if (to < 0 || to >= exercises.length) {
            return;
        }

        const [item] = exercises.splice(from, 1);
        exercises.splice(to, 0, item);
    }

    watch(
        plan,
        () => {
            isDirty.value = true;

            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }

            debounceTimer = setTimeout(() => void save(), AUTOSAVE_DELAY_MS);
        },
        { deep: true },
    );

    // A closed tab must not eat twenty minutes of work.
    // `keepalive` lets the request outlive the document; sendBeacon cannot do
    // this because it only issues POST, and this route is a PUT.
    window.addEventListener('beforeunload', () => {
        if (!isDirty.value || !hasSomethingToSave.value || planId.value === null) {
            return;
        }

        void fetch(route('coach.plans.update', planId.value), {
            method: 'PUT',
            keepalive: true,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                ),
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload(false)),
        });
    });

    return {
        plan,
        planId,
        clientIds,
        isDirty,
        lastSavedAt,
        saveState,
        isGone,
        activeDayIndex,
        activeDay,
        save,
        saveNow,
        saveAndAssign,
        saveAsNew,
        addDay,
        removeDay,
        addExercise,
        removeExercise,
        moveExercise,
    };
}
```

Three things in there are load-bearing and easy to get wrong.

`absorbIds` matches server rows to local rows **by array position**, which is only correct because `WorkoutPlanWriter` writes days and exercises in payload order and returns them ordered by `order_index`. Task 6's `test_order_index_follows_array_position` is what keeps that true.

`inFlight` plus `savePending` collapse concurrent saves into one trailing save rather than queueing them. The payload is the whole tree, so a later save subsumes an earlier one — queueing would only send stale trees.

The `beforeunload` handler fires on a plan that already has an id. A brand-new unsaved plan cannot be rescued this way, which is why the 30-second debounce and the blur-save exist.

- [ ] **Step 2: Write `BuilderToolbar.vue`**

```vue
<script setup lang="ts">
import Badge from '@/components/ui/badge/Badge.vue';
import Button from '@/components/ui/button/Button.vue';
import { Link } from '@inertiajs/vue3';
import IconArrowLeft from '~icons/heroicons/arrow-left';
import IconDuplicate from '~icons/heroicons/document-duplicate';

defineProps<{
    isDirty: boolean;
    saveState: 'idle' | 'saving' | 'error';
    lastSavedAt: Date | null;
    canDuplicate: boolean;
}>();

defineEmits<{ duplicate: [] }>();

const formatTime = (date: Date | null): string =>
    date ? date.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' }) : '';
</script>

<template>
    <div
        class="flex items-center justify-between border-b border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900"
    >
        <Link
            :href="route('coach.plans.index')"
            class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
            data-testid="back-to-plans"
        >
            <IconArrowLeft class="h-4 w-4" />
            {{ $t('Back to plans') }}
        </Link>

        <div class="flex items-center gap-3">
            <Badge
                v-if="saveState === 'error'"
                variant="destructive"
                data-testid="save-badge"
            >
                {{ $t('Error, retrying') }}
            </Badge>
            <Badge v-else-if="saveState === 'saving'" variant="secondary" data-testid="save-badge">
                {{ $t('Saving…') }}
            </Badge>
            <Badge v-else-if="isDirty" variant="outline" data-testid="save-badge">
                {{ $t('Draft') }}
            </Badge>
            <Badge v-else-if="lastSavedAt" variant="secondary" data-testid="save-badge">
                {{ $t('Saved') }} {{ formatTime(lastSavedAt) }}
            </Badge>

            <Button
                v-if="canDuplicate"
                variant="outline"
                size="sm"
                data-testid="duplicate-plan-btn"
                @click="$emit('duplicate')"
            >
                <IconDuplicate class="mr-2 h-4 w-4" />
                {{ $t('Duplicate') }}
            </Button>
        </div>
    </div>
</template>
```

- [ ] **Step 3: Write `DayTabs.vue`**

```vue
<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import IconPlus from '~icons/heroicons/plus';
import IconXMark from '~icons/heroicons/x-mark';
import type { BuilderDay } from '../composables/usePlanBuilder';

defineProps<{
    days: BuilderDay[];
    activeIndex: number;
}>();

defineEmits<{
    select: [index: number];
    add: [];
    remove: [index: number];
}>();
</script>

<template>
    <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 pb-2 dark:border-gray-800">
        <button
            v-for="(day, index) in days"
            :key="index"
            type="button"
            class="group flex items-center gap-2 rounded-md px-3 py-1.5 text-sm transition"
            :class="
                index === activeIndex
                    ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800'
            "
            :data-testid="`day-tab-${day.dayNumber}`"
            @click="$emit('select', index)"
        >
            {{ day.name || `${$t('Day')} ${day.dayNumber}` }}
            <span
                class="opacity-0 group-hover:opacity-100"
                :data-testid="`remove-day-${day.dayNumber}`"
                @click.stop="$emit('remove', index)"
            >
                <IconXMark class="h-3 w-3" />
            </span>
        </button>

        <Button variant="ghost" size="sm" data-testid="add-day-btn" @click="$emit('add')">
            <IconPlus class="mr-1 h-4 w-4" />
            {{ $t('Add day') }}
        </Button>
    </div>
</template>
```

- [ ] **Step 4: Write `PlanExerciseCard.vue`**

```vue
<script setup lang="ts">
import Input from '@/components/ui/input/Input.vue';
import IconArrowDown from '~icons/heroicons/arrow-down';
import IconArrowUp from '~icons/heroicons/arrow-up';
import IconBars from '~icons/heroicons/bars-3';
import IconXMark from '~icons/heroicons/x-mark';
import type { BuilderExercise } from '../composables/usePlanBuilder';

/**
 * Two-way bound so the inline fields never mutate a prop. The parent owns the
 * array; this card owns one entry while it is on screen.
 */
const exercise = defineModel<BuilderExercise>({ required: true });

defineProps<{
    index: number;
    isFirst: boolean;
    isLast: boolean;
}>();

defineEmits<{
    remove: [];
    moveUp: [];
    moveDown: [];
    commit: [];
}>();
</script>

<template>
    <div
        class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900"
        :data-testid="`plan-exercise-${index}`"
    >
        <div class="flex items-center gap-2">
            <IconBars
                class="h-4 w-4 cursor-grab text-gray-400 dark:text-gray-500"
                data-testid="drag-handle"
            />
            <p class="flex-1 truncate font-medium text-gray-900 dark:text-white">
                {{ exercise.name }}
            </p>
            <button
                type="button"
                :disabled="isFirst"
                class="text-gray-400 disabled:opacity-30 dark:text-gray-500"
                :data-testid="`move-up-${index}`"
                :aria-label="$t('Move up')"
                @click="$emit('moveUp')"
            >
                <IconArrowUp class="h-4 w-4" />
            </button>
            <button
                type="button"
                :disabled="isLast"
                class="text-gray-400 disabled:opacity-30 dark:text-gray-500"
                :data-testid="`move-down-${index}`"
                :aria-label="$t('Move down')"
                @click="$emit('moveDown')"
            >
                <IconArrowDown class="h-4 w-4" />
            </button>
            <button
                type="button"
                class="text-gray-400 hover:text-red-600 dark:text-gray-500 dark:hover:text-red-400"
                :data-testid="`remove-exercise-${index}`"
                :aria-label="$t('Remove exercise')"
                @click="$emit('remove')"
            >
                <IconXMark class="h-4 w-4" />
            </button>
        </div>

        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-5">
            <label class="text-xs text-gray-600 dark:text-gray-400">
                {{ $t('Sets') }}
                <Input
                    v-model.number="exercise.sets"
                    type="number"
                    min="1"
                    max="20"
                    :data-testid="`field-sets-${index}`"
                    @blur="$emit('commit')"
                />
            </label>
            <label class="text-xs text-gray-600 dark:text-gray-400">
                {{ $t('Reps') }}
                <Input
                    v-model.number="exercise.repsTarget"
                    type="number"
                    min="1"
                    max="100"
                    :data-testid="`field-reps-${index}`"
                    @blur="$emit('commit')"
                />
            </label>
            <label class="text-xs text-gray-600 dark:text-gray-400">
                {{ $t('Kg') }}
                <Input
                    v-model.number="exercise.weightKg"
                    type="number"
                    step="0.5"
                    min="0"
                    max="999.99"
                    :data-testid="`field-weight-${index}`"
                    @blur="$emit('commit')"
                />
            </label>
            <label class="text-xs text-gray-600 dark:text-gray-400">
                {{ $t('RIR') }}
                <Input
                    v-model.number="exercise.rirTarget"
                    type="number"
                    min="0"
                    max="10"
                    :data-testid="`field-rir-${index}`"
                    @blur="$emit('commit')"
                />
            </label>
            <label class="text-xs text-gray-600 dark:text-gray-400">
                {{ $t('Rest (s)') }}
                <Input
                    v-model.number="exercise.restSeconds"
                    type="number"
                    min="0"
                    max="3600"
                    :data-testid="`field-rest-${index}`"
                    @blur="$emit('commit')"
                />
            </label>
        </div>

        <Input
            v-model="exercise.notes"
            :placeholder="$t('Notes')"
            class="mt-2"
            :data-testid="`field-notes-${index}`"
            @blur="$emit('commit')"
        />
    </div>
</template>
```

- [ ] **Step 5: Write `PlanSettingsPanel.vue`**

```vue
<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import type { BuilderPlan } from '../composables/usePlanBuilder';

const props = defineProps<{
    plan: BuilderPlan;
    clients: Array<{ id: number; name: string }>;
    selectedClientIds: number[];
}>();

const emit = defineEmits<{
    'update:selectedClientIds': [ids: number[]];
    save: [];
    saveAndAssign: [];
    commit: [];
}>();

function toggleClient(id: number): void {
    const next = props.selectedClientIds.includes(id)
        ? props.selectedClientIds.filter((clientId) => clientId !== id)
        : [...props.selectedClientIds, id];

    emit('update:selectedClientIds', next);
}
</script>

<template>
    <div class="flex h-full flex-col gap-4">
        <label class="block text-sm text-gray-600 dark:text-gray-400">
            {{ $t('Plan name') }}
            <Input
                v-model="plan.name"
                data-testid="field-plan-name"
                @blur="emit('commit')"
            />
        </label>

        <label class="block text-sm text-gray-600 dark:text-gray-400">
            {{ $t('Description') }}
            <textarea
                v-model="plan.description"
                rows="4"
                class="mt-1 w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                data-testid="field-plan-description"
                @blur="emit('commit')"
            />
        </label>

        <label class="flex items-center gap-2 text-sm text-gray-900 dark:text-white">
            <input
                v-model="plan.isTemplate"
                type="checkbox"
                data-testid="field-is-template"
                @change="emit('commit')"
            />
            {{ $t('Save as template') }}
        </label>

        <div class="min-h-0 flex-1 overflow-y-auto">
            <p class="mb-2 text-sm text-gray-600 dark:text-gray-400">{{ $t('Assign to athletes') }}</p>
            <label
                v-for="client in clients"
                :key="client.id"
                class="flex items-center gap-2 py-1 text-sm text-gray-900 dark:text-white"
            >
                <input
                    type="checkbox"
                    :checked="selectedClientIds.includes(client.id)"
                    :data-testid="`assign-client-${client.id}`"
                    @change="toggleClient(client.id)"
                />
                {{ client.name }}
            </label>
            <p v-if="clients.length === 0" class="text-sm text-gray-600 dark:text-gray-400">
                {{ $t('No athletes yet.') }}
            </p>
        </div>

        <div class="flex flex-col gap-2">
            <Button data-testid="save-plan-btn" @click="emit('save')">{{ $t('Save') }}</Button>
            <Button
                variant="outline"
                :disabled="selectedClientIds.length === 0"
                data-testid="save-assign-btn"
                @click="emit('saveAndAssign')"
            >
                {{ $t('Save and assign') }}
            </Button>
        </div>
    </div>
</template>
```

- [ ] **Step 6: Write `Builder.vue`**

```vue
<script setup lang="ts">
import CoachLayout from '@/layouts/CoachLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

import BuilderToolbar from '../../../components/BuilderToolbar.vue';
import DayTabs from '../../../components/DayTabs.vue';
import ExerciseLibrary from '../../../components/ExerciseLibrary.vue';
import PlanExerciseCard from '../../../components/PlanExerciseCard.vue';
import PlanSettingsPanel from '../../../components/PlanSettingsPanel.vue';
import { toBuilderPlan, usePlanBuilder } from '../../../composables/usePlanBuilder';
import { useSortableList } from '../../../composables/useSortableList';
import type { ExerciseListItem } from '../../../types/exercises';

const props = defineProps<{
    plan: Parameters<typeof toBuilderPlan>[0];
    exercises: ExerciseListItem[];
    clients: Array<{ id: number; name: string }>;
    assignedClientIds?: number[];
}>();

const builder = usePlanBuilder(toBuilderPlan(props.plan), props.plan?.id ?? null);

builder.clientIds.value = props.assignedClientIds ?? [];

const dayListEl = ref<HTMLElement | null>(null);

useSortableList(dayListEl, {
    group: 'exercises',
    handle: '[data-testid="drag-handle"]',
    onReorder: (from, to) => builder.moveExercise(builder.activeDayIndex.value, from, to),
    onAdd: (index, node) => {
        // The dropped node is a clone of an ExerciseCard, which carries its id.
        const exerciseId = Number(node.dataset.exerciseId);
        const source = props.exercises.find((exercise) => exercise.id === exerciseId);

        if (source) {
            builder.addExercise(source, index);
        }
    },
});

function duplicate(): void {
    if (builder.planId.value !== null) {
        router.post(route('coach.plans.duplicate', builder.planId.value));
    }
}
</script>

<template>
    <CoachLayout
        :title="plan?.name ?? $t('New plan')"
        :breadcrumbs="[{ title: $t('Workout Plans'), url: route('coach.plans.index') }, { title: plan?.name ?? $t('New plan') }]"
    >
        <BuilderToolbar
            :is-dirty="builder.isDirty.value"
            :save-state="builder.saveState.value"
            :last-saved-at="builder.lastSavedAt.value"
            :can-duplicate="builder.planId.value !== null"
            @duplicate="duplicate"
        />

        <div
            v-if="builder.isGone.value"
            class="border-b border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200"
            data-testid="plan-gone-banner"
        >
            {{ $t('This plan no longer exists.') }}
            <button
                type="button"
                class="ml-2 font-medium underline"
                data-testid="save-as-new-btn"
                @click="builder.saveAsNew()"
            >
                {{ $t('Save as new') }}
            </button>
        </div>

        <div class="flex flex-1 gap-4 p-4">
            <aside class="hidden w-[280px] shrink-0 lg:block" data-testid="library-column">
                <div ref="libraryEl">
                    <ExerciseLibrary
                        :exercises="exercises"
                        variant="sidebar"
                        @add="builder.addExercise($event)"
                    />
                </div>
            </aside>

            <main class="min-w-0 flex-1">
                <DayTabs
                    :days="builder.plan.days"
                    :active-index="builder.activeDayIndex.value"
                    @select="builder.activeDayIndex.value = $event"
                    @add="builder.addDay()"
                    @remove="builder.removeDay($event)"
                />

                <div
                    v-if="builder.activeDay.value"
                    ref="dayListEl"
                    class="mt-4 flex flex-col gap-3"
                    data-testid="day-exercise-list"
                >
                    <PlanExerciseCard
                        v-for="(exercise, index) in builder.activeDay.value.exercises"
                        :key="exercise.id ?? `new-${index}`"
                        v-model="builder.activeDay.value.exercises[index]"
                        :index="index"
                        :is-first="index === 0"
                        :is-last="index === builder.activeDay.value.exercises.length - 1"
                        @remove="builder.removeExercise(builder.activeDayIndex.value, index)"
                        @move-up="builder.moveExercise(builder.activeDayIndex.value, index, index - 1)"
                        @move-down="builder.moveExercise(builder.activeDayIndex.value, index, index + 1)"
                        @commit="builder.saveNow()"
                    />

                    <p
                        v-if="builder.activeDay.value.exercises.length === 0"
                        class="rounded-lg border border-dashed border-gray-200 py-16 text-center text-gray-600 dark:border-gray-800 dark:text-gray-400"
                        data-testid="day-empty"
                    >
                        {{ $t('Drag exercises here, or use the + button.') }}
                    </p>
                </div>

                <p
                    v-else
                    class="mt-4 rounded-lg border border-dashed border-gray-200 py-16 text-center text-gray-600 dark:border-gray-800 dark:text-gray-400"
                    data-testid="no-days"
                >
                    {{ $t('Add a day to get started.') }}
                </p>
            </main>

            <aside class="hidden w-[240px] shrink-0 xl:block" data-testid="settings-column">
                <PlanSettingsPanel
                    :plan="builder.plan"
                    :clients="clients"
                    :selected-client-ids="builder.clientIds.value"
                    @update:selected-client-ids="builder.clientIds.value = $event"
                    @save="builder.saveNow()"
                    @save-and-assign="builder.saveAndAssign()"
                    @commit="builder.saveNow()"
                />
            </aside>
        </div>
    </CoachLayout>
</template>
```

- [ ] **Step 7: Feed the builder its props**

`PlanBuilderController@create` and `@edit` must both pass `exercises`. Add to each render array — note the `->toArray($request)`: a raw `JsonResource`/collection handed to `Inertia::render` is resolved through `toResponse()`, which wraps it in a `data` key, so `props.exercises` would arrive as `{data: [...]}` instead of `[...]`. Calling `->toArray($request)` yields the bare array the components expect. (This is the same fix already applied to the `plan` prop in `edit()` — commit `5ddedb3` — and the pattern `Coach\ExerciseController@index` uses.)

```php
            'exercises' => ExerciseResource::collection(
                Exercise::active()->forCoach($request->user()->coach->id)->orderBy('name')->get()
            )->toArray($request),
```

`create()` currently has no `$request` variable in scope in the committed code (it reads `$request->user()` — it does have `Request $request`). Confirm the signature is `create(Request $request)` and `edit(Request $request, WorkoutPlan $plan)` — both already take `$request`, so `->toArray($request)` resolves.

Then confirm `WorkoutPlanDetailResource` emits the exact shape `toBuilderPlan` expects. Open `modules/WorkoutPlans/app/Http/Resources/WorkoutPlanDetailResource.php` and check it produces camelCase `durationWeeks`, `sessionsPerWeek`, `isTemplate`, a `days` array whose entries carry `id`, `dayNumber`, `name`, and an `exercises` array whose entries carry `id`, `exerciseId`, a nested `exercise` object with at least `name`, plus `sets`, `repsTarget`, `weightKg`, `rirTarget`, `restSeconds`, `notes`.

If any key is missing or snake_cased, fix the resource — the mobile app reads the same resource, so add keys rather than renaming existing ones, and run the API tests afterwards:

Run: `php -d memory_limit=2048M artisan test --compact --filter=WorkoutPlanControllerTest`

Expected: PASS.

- [ ] **Step 8: Verify it builds**

Run: `npx vue-tsc --noEmit -p tsconfig.json && npm run build`

Expected: no errors.

Then load the page by hand: `composer dev`, log in as a coach, visit `/coach/plans/create`. Add a day, drag an exercise in, edit a field, blur, and confirm the badge flips from "Bozza" to "Salvato HH:mm". Confirm no duplicated card appears after the drag.

- [ ] **Step 9: Commit**

```bash
npm run lint && npm run format
vendor/bin/pint --dirty --format agent
git add modules/WorkoutPlans/resources/js modules/WorkoutPlans/app/Http/Controllers/Coach/PlanBuilderController.php
git commit -m "feat(workoutplans): add three-column plan builder with autosave"
```

---

### Task 14: Standalone exercise library page

**Files:**
- Create: `modules/WorkoutPlans/resources/js/pages/Coach/Exercises/Index.vue`
- Create: `modules/WorkoutPlans/resources/js/components/ExerciseFormModal.vue`

**Interfaces:**
- Consumes: `ExerciseLibrary` (Task 11), `coach.exercises.store|update|destroy` (Task 9).
- Produces: nothing.

- [ ] **Step 1: Write `ExerciseFormModal.vue`**

> ⚠️ CORRECTION (found during execution): the `useForm` version below is WRONG. `Coach\ExerciseController@store/@update` return JSON (201), not an Inertia redirect, so an Inertia `useForm` submit rejects the JSON success. The modal must submit via `fetch` (like `usePlanBuilder.ts`), reading the 422 `{error:{fields}}` shape. See `.superpowers/sdd/task-14-brief.md` for the corrected component. Controller stays unchanged (its JSON contract is tested).

```vue
<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import Dialog from '@/components/ui/dialog/Dialog.vue';
import DialogContent from '@/components/ui/dialog/DialogContent.vue';
import Input from '@/components/ui/input/Input.vue';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import type { ExerciseListItem } from '../types/exercises';

const props = defineProps<{
    open: boolean;
    exercise: ExerciseListItem | null;
}>();

const emit = defineEmits<{ close: []; saved: [] }>();

const form = useForm({
    name: '',
    type: '',
    primaryMuscles: [] as string[],
    videoUrl: '',
    instructions: '',
});

watch(
    () => props.exercise,
    (exercise) => {
        form.defaults({
            name: exercise?.name ?? '',
            type: exercise?.category ?? '',
            primaryMuscles: exercise?.primaryMuscles ?? [],
            videoUrl: exercise?.videoUrl ?? '',
            instructions: exercise?.instructions ?? '',
        });
        form.reset();
    },
    { immediate: true },
);

function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            emit('close');
        },
    };

    if (props.exercise) {
        form.put(route('coach.exercises.update', props.exercise.id), options);
    } else {
        form.post(route('coach.exercises.store'), options);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('close')">
        <DialogContent class="bg-white dark:bg-gray-900" data-testid="exercise-form-modal">
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <label class="text-sm text-gray-600 dark:text-gray-400">
                    {{ $t('Name') }}
                    <Input v-model="form.name" data-testid="exercise-form-name" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-600 dark:text-red-400">
                        {{ form.errors.name }}
                    </p>
                </label>

                <label class="text-sm text-gray-600 dark:text-gray-400">
                    {{ $t('Video URL') }}
                    <Input
                        v-model="form.videoUrl"
                        placeholder="https://youtu.be/…"
                        data-testid="exercise-form-video"
                    />
                    <p v-if="form.errors.videoUrl" class="mt-1 text-xs text-red-600 dark:text-red-400" data-testid="exercise-form-video-error">
                        {{ form.errors.videoUrl }}
                    </p>
                </label>

                <label class="text-sm text-gray-600 dark:text-gray-400">
                    {{ $t('Instructions') }}
                    <textarea
                        v-model="form.instructions"
                        rows="4"
                        class="mt-1 w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                        data-testid="exercise-form-instructions"
                    />
                </label>

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="emit('close')">
                        {{ $t('Cancel') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing" data-testid="exercise-form-submit">
                        {{ $t('Save') }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
```

- [ ] **Step 2: Write `Coach/Exercises/Index.vue`**

```vue
<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import CoachLayout from '@/layouts/CoachLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import IconPlus from '~icons/heroicons/plus';

import ExerciseFormModal from '../../../components/ExerciseFormModal.vue';
import ExerciseLibrary from '../../../components/ExerciseLibrary.vue';
import type { ExerciseListItem } from '../../../types/exercises';

defineProps<{ exercises: ExerciseListItem[] }>();

const editing = ref<ExerciseListItem | null>(null);
const modalOpen = ref(false);
const deleteError = ref<string | null>(null);

function create(): void {
    editing.value = null;
    modalOpen.value = true;
}

function edit(exercise: ExerciseListItem): void {
    editing.value = exercise;
    modalOpen.value = true;
}

function remove(exercise: ExerciseListItem): void {
    deleteError.value = null;

    router.delete(route('coach.exercises.destroy', exercise.id), {
        preserveScroll: true,
        onError: (errors) => {
            deleteError.value = errors.exercise ?? null;
        },
    });
}

const title = 'Exercises';
</script>

<template>
    <CoachLayout
        :title="title"
        :breadcrumbs="[{ title: $t('Workout Plans'), url: route('coach.plans.index') }, { title: $t('Exercises') }]"
    >
        <div class="flex flex-1 flex-col gap-6 p-6 pt-2">
            <div class="flex items-start justify-between">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                    {{ $t('Exercises') }}
                </h1>
                <Button data-testid="new-exercise-btn" @click="create">
                    <IconPlus class="mr-2 h-4 w-4" />
                    {{ $t('New exercise') }}
                </Button>
            </div>

            <p
                v-if="deleteError"
                class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 dark:bg-red-950 dark:text-red-200"
                data-testid="delete-error"
            >
                {{ deleteError }}
            </p>

            <ExerciseLibrary
                :exercises="exercises"
                variant="grid"
                @add="() => {}"
                @edit="edit"
                @remove="remove"
            />
        </div>

        <ExerciseFormModal
            :open="modalOpen"
            :exercise="editing"
            @close="modalOpen = false"
            @saved="router.reload({ only: ['exercises'] })"
        />
    </CoachLayout>
</template>
```

The `@add` handler is a no-op on this page — there is no day to add to. `ExerciseCard` already hides the `+` button outside the sidebar variant (Task 11), and `ExerciseLibrary` only binds a drag source in the sidebar variant, so the grid here is a plain list.

`onError` reads `errors.exercise` because the web `destroy` redirects back with `withErrors(['exercise' => …])`, which Inertia surfaces as `page.props.errors.exercise`.

- [ ] **Step 3: Verify**

Run: `npx vue-tsc --noEmit -p tsconfig.json && npm run build`
Run: `php -d memory_limit=2048M artisan test --compact --filter=Coach\\ExerciseControllerTest`

Expected: both pass.

- [ ] **Step 4: Commit**

```bash
npm run lint && npm run format
git add modules/WorkoutPlans/resources/js
git commit -m "feat(workoutplans): add standalone exercise library page"
```

---

### Task 15: End-to-end tests

The drag reconciliation bug and the auto-save cycle are invisible to PHPUnit. These are the tests that catch them.

**Files:**
- Create: `modules/WorkoutPlans/tests/e2e/builder.spec.ts`
- Create: `modules/WorkoutPlans/tests/e2e/exercise-library.spec.ts`

**Interfaces:**
- Consumes: every `data-testid` defined in Tasks 11–14, and `tests/e2e/helpers/auth.ts`'s `loginAs`.
- Produces: nothing.

- [ ] **Step 1: Write the builder spec**

Create `modules/WorkoutPlans/tests/e2e/builder.spec.ts`:

```typescript
import { expect } from '@playwright/test';
import { test } from '../../../../tests/e2e/fixtures';
import { loginAs } from '../../../../tests/e2e/helpers/auth';

const COACH = { email: 'coach@miocoach.dev', password: 'secretsauce' };

test.describe('Plan builder', () => {
    test.beforeEach(async ({ page, laravel }) => {
        await loginAs(page, laravel, COACH);
        await page.goto('/coach/plans/create');
    });

    test('adding an exercise with the + button creates exactly one card', async ({ page }) => {
        await page.getByTestId('add-day-btn').click();

        const firstCard = page.getByTestId('library-list').locator('[data-testid^="add-exercise-"]').first();
        await firstCard.click();

        await expect(page.getByTestId('day-exercise-list').locator('[data-testid^="plan-exercise-"]')).toHaveCount(1);
    });

    test('dragging from the library into a day creates exactly one card and no ghost', async ({ page }) => {
        await page.getByTestId('add-day-btn').click();

        const source = page.getByTestId('library-list').locator('[data-testid^="exercise-card-"]').first();
        const target = page.getByTestId('day-exercise-list');

        await source.dragTo(target);

        await expect(target.locator('[data-testid^="plan-exercise-"]')).toHaveCount(1);
        // The library keeps its card: the drag clones, it does not move.
        await expect(page.getByTestId('library-list').locator('[data-testid^="exercise-card-"]').first()).toBeVisible();
    });

    test('the save badge flips from Draft to Saved', async ({ page }) => {
        await page.getByTestId('field-plan-name').fill('Piano E2E');
        await expect(page.getByTestId('save-badge')).toHaveText(/Bozza|Draft/);

        await page.getByTestId('save-plan-btn').click();

        await expect(page.getByTestId('save-badge')).toHaveText(/Salvato|Saved/, { timeout: 5_000 });
    });

    test('reordering with the arrow buttons persists across a reload', async ({ page }) => {
        await page.getByTestId('add-day-btn').click();

        const addButtons = page.getByTestId('library-list').locator('[data-testid^="add-exercise-"]');
        await addButtons.nth(0).click();
        await addButtons.nth(1).click();

        await page.getByTestId('field-plan-name').fill('Ordine');
        await page.getByTestId('save-plan-btn').click();
        await expect(page.getByTestId('save-badge')).toHaveText(/Salvato|Saved/);

        const firstName = await page.getByTestId('plan-exercise-0').locator('p').first().innerText();

        await page.getByTestId('move-down-0').click();
        await page.getByTestId('save-plan-btn').click();
        await expect(page.getByTestId('save-badge')).toHaveText(/Salvato|Saved/);

        await page.reload();

        await expect(page.getByTestId('plan-exercise-1').locator('p').first()).toHaveText(firstName);
    });

    test('editing an inline field and blurring saves immediately', async ({ page }) => {
        await page.getByTestId('add-day-btn').click();
        await page.getByTestId('library-list').locator('[data-testid^="add-exercise-"]').first().click();
        await page.getByTestId('field-plan-name').fill('Blur');
        await page.getByTestId('save-plan-btn').click();
        await expect(page.getByTestId('save-badge')).toHaveText(/Salvato|Saved/);

        await page.getByTestId('field-sets-0').fill('5');
        await page.getByTestId('field-sets-0').blur();

        await expect(page.getByTestId('save-badge')).toHaveText(/Salvato|Saved/, { timeout: 5_000 });

        await page.reload();
        await expect(page.getByTestId('field-sets-0')).toHaveValue('5');
    });
});
```

- [ ] **Step 2: Write the library spec**

Create `modules/WorkoutPlans/tests/e2e/exercise-library.spec.ts`:

```typescript
import { expect } from '@playwright/test';
import { test } from '../../../../tests/e2e/fixtures';
import { loginAs } from '../../../../tests/e2e/helpers/auth';

const COACH = { email: 'coach@miocoach.dev', password: 'secretsauce' };

test.describe('Exercise library', () => {
    test.beforeEach(async ({ page, laravel }) => {
        await loginAs(page, laravel, COACH);
        await page.goto('/coach/exercises');
    });

    test('search filters the grid', async ({ page }) => {
        const before = await page.getByTestId('library-list').locator('[data-testid^="exercise-card-"]').count();

        await page.getByTestId('library-search').fill('zzzz-nothing-matches');

        await expect(page.getByTestId('library-empty')).toBeVisible();
        expect(before).toBeGreaterThan(0);
    });

    test('a disallowed video host shows a validation error', async ({ page }) => {
        await page.getByTestId('new-exercise-btn').click();
        await page.getByTestId('exercise-form-name').fill('Sospetto');
        await page.getByTestId('exercise-form-video').fill('https://evil.example.com/v.mp4');
        await page.getByTestId('exercise-form-submit').click();

        await expect(page.getByTestId('exercise-form-video-error')).toBeVisible();
    });

    test('a coach cannot edit a global exercise', async ({ page }) => {
        // Global cards render no actions menu at all.
        const globalCard = page.getByTestId('library-list').locator('[data-testid^="exercise-card-"]').first();

        await expect(globalCard.locator('[data-testid^="exercise-actions-"]')).toHaveCount(0);
    });
});
```

The last test assumes the seeded global exercises sort first. If the coach has custom exercises in the seed, target a known global one by name instead of `.first()`.

- [ ] **Step 3: Run them**

Run: `npx playwright test --project="@WorkoutPlans*"`

Expected: PASS.

The drag test is the one that will fail if `useSortableList`'s DOM revert is wrong. If `toHaveCount(1)` reports 2, the clone was not removed — go back to `onAdd` in Task 10 and check `event.item.remove()` runs before the array mutation.

If `dragTo` does not trigger SortableJS (it uses mouse events that HTML5 drag emulation may not produce), fall back to explicit mouse steps:

```typescript
const box = await source.boundingBox();
const targetBox = await target.boundingBox();
await page.mouse.move(box!.x + box!.width / 2, box!.y + box!.height / 2);
await page.mouse.down();
await page.mouse.move(targetBox!.x + 20, targetBox!.y + 20, { steps: 10 });
await page.mouse.up();
```

- [ ] **Step 4: Run the whole suite**

```bash
php -d memory_limit=2048M artisan test --testsuite=Modules --filter='Modules\\WorkoutPlans\\Tests'
composer analyse
npx playwright test --project="@WorkoutPlans*"
```

Expected: all green. `composer analyse` runs PHPStan level 5 — fix any new errors it reports in the files this plan touched.

- [ ] **Step 5: Commit**

```bash
npm run lint && npm run format
git add modules/WorkoutPlans/tests/e2e
git commit -m "test(workoutplans): add e2e coverage for builder and exercise library"
```

---

## Verification

Before considering this plan done:

```bash
php -d memory_limit=2048M artisan test --testsuite=Modules --filter='Modules\\WorkoutPlans\\Tests'
php -d memory_limit=2048M artisan test --compact
composer analyse
composer lint
npm run lint
npm run build
npx playwright test --project="@WorkoutPlans*"
```

Every command must pass **except `composer analyse`**, which is not green on `main`: PHPStan level 5 already reports ~666 errors across `modules/` on the base branch, almost all the systemic untyped-relation pattern (`User::coach()` and siblings return an untyped `Relation`). This plan does not fix that repo-wide condition. The bar for `composer analyse` is therefore *no new errors beyond the main baseline attributable to this work's own new files* — measure with `git checkout main -- . && vendor/bin/phpstan analyse modules --level=5` for a baseline count, then compare on the branch. Do not claim completion on a partial run of the other commands.

## Known follow-ups

Recorded in the spec, deliberately not built here:

1. **AI load progression.** `CalculateAiProgressionJob` stays a stub that only logs. Note for whoever picks it up: `personal_records` is a current-max store (unique on `client_id, exercise_id`), not a history — the "last 5 sessions" table must come from `session_sets` joined to `workout_sessions`.
2. **Native video upload.** Presigned S3, size limits, ffmpeg thumbnails.
3. **Multi-week periodization.** Gives `week_number` and `duration_weeks` real meaning.
