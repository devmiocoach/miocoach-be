# WorkoutPlans — Full Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement full API layer (exercises, workout plans with days, workout sessions with personal records) and update Filament admin for the WorkoutPlans module.

**Architecture:** Three API controllers under `Modules\WorkoutPlans\Http\Controllers\Api\V1\`. Five new tables (workout_days, plan_assignments, workout_sessions, session_sets, personal_records). WorkoutExercise changed Pivot→Model to support HasMany from WorkoutDay. Session completion runs in a DB transaction: bulk insert sets, calculate volume, upsert personal records.

**Tech Stack:** Laravel 13, PHP 8.4+, PHPUnit 12, Filament 5. Auth: `jwt.auth` + `verified.email` middleware (matching Users module pattern).

---

## File Map

**Migrations (7 new):**
- `modules/WorkoutPlans/database/migrations/2026_06_02_000001_create_workout_days_table.php`
- `modules/WorkoutPlans/database/migrations/2026_06_02_000002_add_muscles_to_exercises_table.php`
- `modules/WorkoutPlans/database/migrations/2026_06_02_000003_add_fields_to_workout_exercises_table.php`
- `modules/WorkoutPlans/database/migrations/2026_06_02_000004_create_plan_assignments_table.php`
- `modules/WorkoutPlans/database/migrations/2026_06_02_000005_create_workout_sessions_table.php`
- `modules/WorkoutPlans/database/migrations/2026_06_02_000006_create_session_sets_table.php`
- `modules/WorkoutPlans/database/migrations/2026_06_02_000007_create_personal_records_table.php`

**Models (3 updated, 5 new):**
- Modify: `modules/WorkoutPlans/app/Models/Exercise.php`
- Modify: `modules/WorkoutPlans/app/Models/WorkoutExercise.php` (Pivot→Model, new fields)
- Modify: `modules/WorkoutPlans/app/Models/WorkoutPlan.php`
- Create: `modules/WorkoutPlans/app/Models/WorkoutDay.php`
- Create: `modules/WorkoutPlans/app/Models/PlanAssignment.php`
- Create: `modules/WorkoutPlans/app/Models/WorkoutSession.php`
- Create: `modules/WorkoutPlans/app/Models/SessionSet.php`
- Create: `modules/WorkoutPlans/app/Models/PersonalRecord.php`

**Factories (2 new):**
- Create: `modules/WorkoutPlans/database/factories/WorkoutDayFactory.php`
- Create: `modules/WorkoutPlans/database/factories/WorkoutSessionFactory.php`
- Modify: `modules/WorkoutPlans/database/factories/ExerciseFactory.php`

**API Controllers (3 new):**
- Create: `modules/WorkoutPlans/app/Http/Controllers/Api/V1/ExerciseController.php`
- Create: `modules/WorkoutPlans/app/Http/Controllers/Api/V1/WorkoutPlanController.php`
- Create: `modules/WorkoutPlans/app/Http/Controllers/Api/V1/WorkoutSessionController.php`

**Resources (6 new):**
- Create: `modules/WorkoutPlans/app/Http/Resources/ExerciseResource.php`
- Create: `modules/WorkoutPlans/app/Http/Resources/WorkoutPlanResource.php`
- Create: `modules/WorkoutPlans/app/Http/Resources/WorkoutPlanDetailResource.php`
- Create: `modules/WorkoutPlans/app/Http/Resources/WorkoutDayResource.php`
- Create: `modules/WorkoutPlans/app/Http/Resources/WorkoutExerciseResource.php`
- Create: `modules/WorkoutPlans/app/Http/Resources/WorkoutSessionResource.php`

**Requests (7 new):**
- Create: `modules/WorkoutPlans/app/Http/Requests/StoreExerciseRequest.php`
- Create: `modules/WorkoutPlans/app/Http/Requests/UpdateExerciseRequest.php`
- Create: `modules/WorkoutPlans/app/Http/Requests/StoreWorkoutPlanRequest.php`
- Create: `modules/WorkoutPlans/app/Http/Requests/UpdateWorkoutPlanRequest.php`
- Create: `modules/WorkoutPlans/app/Http/Requests/AssignWorkoutPlanRequest.php`
- Create: `modules/WorkoutPlans/app/Http/Requests/StartWorkoutSessionRequest.php`
- Create: `modules/WorkoutPlans/app/Http/Requests/CompleteWorkoutSessionRequest.php`

**Jobs (1 new):**
- Create: `modules/WorkoutPlans/app/Jobs/CalculateAiProgressionJob.php`

**Routes:**
- Modify: `modules/WorkoutPlans/routes/api.php`

**Filament (2 modified, 3 new):**
- Modify: `modules/WorkoutPlans/app/Filament/Resources/Exercises/Schemas/ExerciseForm.php`
- Modify: `modules/WorkoutPlans/app/Filament/Resources/WorkoutPlans/Schemas/WorkoutPlanForm.php`
- Create: `modules/WorkoutPlans/app/Filament/Resources/WorkoutSessions/WorkoutSessionResource.php`
- Create: `modules/WorkoutPlans/app/Filament/Resources/WorkoutSessions/Pages/ListWorkoutSessions.php`
- Create: `modules/WorkoutPlans/app/Filament/Resources/WorkoutSessions/Tables/WorkoutSessionsTable.php`

**Tests (3 new):**
- Create: `modules/WorkoutPlans/tests/Feature/Api/ExerciseControllerTest.php`
- Create: `modules/WorkoutPlans/tests/Feature/Api/WorkoutPlanControllerTest.php`
- Create: `modules/WorkoutPlans/tests/Feature/Api/WorkoutSessionControllerTest.php`

---

## Task 1: Migrations — workout_days + exercises columns

**Files:**
- Create: `modules/WorkoutPlans/database/migrations/2026_06_02_000001_create_workout_days_table.php`
- Create: `modules/WorkoutPlans/database/migrations/2026_06_02_000002_add_muscles_to_exercises_table.php`

- [ ] **Step 1: Create workout_days migration**

```php
<?php
// modules/WorkoutPlans/database/migrations/2026_06_02_000001_create_workout_days_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_plan_id')->constrained('workout_plans')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_number');
            $table->string('name')->nullable();
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->timestamps();

            $table->index(['workout_plan_id', 'order_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_days');
    }
};
```

- [ ] **Step 2: Create exercises muscles migration**

```php
<?php
// modules/WorkoutPlans/database/migrations/2026_06_02_000002_add_muscles_to_exercises_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->json('primary_muscles')->nullable()->after('muscle_groups');
            $table->json('secondary_muscles')->nullable()->after('primary_muscles');
        });
    }

    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropColumn(['primary_muscles', 'secondary_muscles']);
        });
    }
};
```

- [ ] **Step 3: Run migrations to verify no errors**

```bash
php artisan module:migrate WorkoutPlans
```

Expected: migrations run without errors.

- [ ] **Step 4: Commit**

```bash
git add modules/WorkoutPlans/database/migrations/2026_06_02_000001_create_workout_days_table.php modules/WorkoutPlans/database/migrations/2026_06_02_000002_add_muscles_to_exercises_table.php
git commit -m "feat(workout-plans): add workout_days table and muscles columns to exercises"
```

---

## Task 2: Migrations — workout_exercises fields + plan_assignments

**Files:**
- Create: `modules/WorkoutPlans/database/migrations/2026_06_02_000003_add_fields_to_workout_exercises_table.php`
- Create: `modules/WorkoutPlans/database/migrations/2026_06_02_000004_create_plan_assignments_table.php`

- [ ] **Step 1: Create workout_exercises additive migration**

```php
<?php
// modules/WorkoutPlans/database/migrations/2026_06_02_000003_add_fields_to_workout_exercises_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->foreignId('workout_day_id')->nullable()->after('workout_plan_id')->constrained('workout_days')->nullOnDelete();
            $table->unsignedSmallInteger('reps_target')->nullable()->after('reps');
            $table->decimal('weight_kg', 5, 2)->nullable()->after('reps_target');
            $table->unsignedTinyInteger('rir_target')->nullable()->after('weight_kg');
            $table->unsignedSmallInteger('order_index')->default(0)->after('order');
        });
    }

    public function down(): void
    {
        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->dropForeign(['workout_day_id']);
            $table->dropColumn(['workout_day_id', 'reps_target', 'weight_kg', 'rir_target', 'order_index']);
        });
    }
};
```

- [ ] **Step 2: Create plan_assignments migration**

```php
<?php
// modules/WorkoutPlans/database/migrations/2026_06_02_000004_create_plan_assignments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_plan_id')->constrained('workout_plans')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->date('active_from')->nullable();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamps();

            $table->unique(['workout_plan_id', 'client_id']);
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_assignments');
    }
};
```

- [ ] **Step 3: Run and verify**

```bash
php artisan module:migrate WorkoutPlans
```

- [ ] **Step 4: Commit**

```bash
git add modules/WorkoutPlans/database/migrations/2026_06_02_000003_add_fields_to_workout_exercises_table.php modules/WorkoutPlans/database/migrations/2026_06_02_000004_create_plan_assignments_table.php
git commit -m "feat(workout-plans): add prescription fields to workout_exercises, add plan_assignments table"
```

---

## Task 3: Migrations — sessions, sets, personal records

**Files:**
- Create: `modules/WorkoutPlans/database/migrations/2026_06_02_000005_create_workout_sessions_table.php`
- Create: `modules/WorkoutPlans/database/migrations/2026_06_02_000006_create_session_sets_table.php`
- Create: `modules/WorkoutPlans/database/migrations/2026_06_02_000007_create_personal_records_table.php`

- [ ] **Step 1: workout_sessions migration**

```php
<?php
// modules/WorkoutPlans/database/migrations/2026_06_02_000005_create_workout_sessions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('workout_plan_id')->nullable()->constrained('workout_plans')->nullOnDelete();
            $table->foreignId('workout_day_id')->nullable()->constrained('workout_days')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->decimal('total_volume_kg', 10, 2)->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'started_at']);
            $table->index('workout_plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_sessions');
    }
};
```

- [ ] **Step 2: session_sets migration**

```php
<?php
// modules/WorkoutPlans/database/migrations/2026_06_02_000006_create_session_sets_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_session_id')->constrained('workout_sessions')->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained('exercises')->cascadeOnDelete();
            $table->unsignedTinyInteger('set_number');
            $table->unsignedSmallInteger('reps_done')->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->tinyInteger('rir_actual')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamps();

            $table->index(['workout_session_id', 'exercise_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_sets');
    }
};
```

- [ ] **Step 3: personal_records migration**

```php
<?php
// modules/WorkoutPlans/database/migrations/2026_06_02_000007_create_personal_records_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained('exercises')->cascadeOnDelete();
            $table->decimal('weight_kg', 5, 2);
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique(['client_id', 'exercise_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_records');
    }
};
```

- [ ] **Step 4: Run and verify**

```bash
php artisan module:migrate WorkoutPlans
```

- [ ] **Step 5: Commit**

```bash
git add modules/WorkoutPlans/database/migrations/2026_06_02_000005_create_workout_sessions_table.php modules/WorkoutPlans/database/migrations/2026_06_02_000006_create_session_sets_table.php modules/WorkoutPlans/database/migrations/2026_06_02_000007_create_personal_records_table.php
git commit -m "feat(workout-plans): add workout_sessions, session_sets, personal_records tables"
```

---

## Task 4: Update existing models

**Files:**
- Modify: `modules/WorkoutPlans/app/Models/Exercise.php`
- Modify: `modules/WorkoutPlans/app/Models/WorkoutExercise.php`
- Modify: `modules/WorkoutPlans/app/Models/WorkoutPlan.php`

- [ ] **Step 1: Update Exercise model — add primary_muscles and secondary_muscles**

Add to `$fillable` array (after `muscle_groups`):
```php
'primary_muscles',
'secondary_muscles',
```

Add to `casts()` method:
```php
'primary_muscles' => 'array',
'secondary_muscles' => 'array',
```

Add to PHPDoc block:
```php
 * @property array<string>|null $primary_muscles
 * @property array<string>|null $secondary_muscles
```

- [ ] **Step 2: Rewrite WorkoutExercise model — Pivot to Model, add new fields**

Replace the entire file:

```php
<?php

namespace Modules\WorkoutPlans\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $workout_plan_id
 * @property int|null $workout_day_id
 * @property int $exercise_id
 * @property int|null $week_number
 * @property int|null $day_number
 * @property int $order
 * @property int $order_index
 * @property int|null $sets
 * @property int|null $reps
 * @property int|null $reps_target
 * @property float|null $weight_kg
 * @property int|null $rir_target
 * @property int|null $duration_seconds
 * @property int|null $rest_seconds
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WorkoutExercise extends Model
{
    protected $table = 'workout_exercises';

    protected $fillable = [
        'workout_plan_id',
        'workout_day_id',
        'exercise_id',
        'week_number',
        'day_number',
        'order',
        'order_index',
        'sets',
        'reps',
        'reps_target',
        'weight_kg',
        'rir_target',
        'duration_seconds',
        'rest_seconds',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'week_number' => 'integer',
            'day_number' => 'integer',
            'order' => 'integer',
            'order_index' => 'integer',
            'sets' => 'integer',
            'reps' => 'integer',
            'reps_target' => 'integer',
            'weight_kg' => 'float',
            'rir_target' => 'integer',
            'duration_seconds' => 'integer',
            'rest_seconds' => 'integer',
        ];
    }

    /** @return BelongsTo<WorkoutPlan, $this> */
    public function workoutPlan(): BelongsTo
    {
        return $this->belongsTo(WorkoutPlan::class);
    }

    /** @return BelongsTo<WorkoutDay, $this> */
    public function workoutDay(): BelongsTo
    {
        return $this->belongsTo(WorkoutDay::class);
    }

    /** @return BelongsTo<Exercise, $this> */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
```

- [ ] **Step 3: Update WorkoutPlan model — add workoutDays HasMany, remove BelongsToMany exercises()**

Add import: `use Illuminate\Database\Eloquent\Relations\HasManyThrough;`

Replace the `exercises()` BelongsToMany method with:

```php
/** @return HasMany<WorkoutDay, $this> */
public function workoutDays(): HasMany
{
    return $this->hasMany(WorkoutDay::class)->orderBy('order_index');
}

/** @return HasManyThrough<WorkoutExercise, WorkoutDay, $this> */
public function allWorkoutExercises(): HasManyThrough
{
    return $this->hasManyThrough(WorkoutExercise::class, WorkoutDay::class);
}
```

Keep the existing `workoutExercises() HasMany` as-is (used by Filament legacy flat repeater).

Also update the `workoutExercises()` ordering to use `order_index`:
```php
public function workoutExercises(): HasMany
{
    return $this->hasMany(WorkoutExercise::class)
        ->orderBy('week_number')
        ->orderBy('day_number')
        ->orderBy('order_index');
}
```

- [ ] **Step 4: Run existing tests to verify no regressions**

```bash
php -d memory_limit=2048M artisan test --compact --testsuite=Modules --filter='^Modules\WorkoutPlans\Tests'
```

Expected: all existing tests pass.

- [ ] **Step 5: Commit**

```bash
git add modules/WorkoutPlans/app/Models/
git commit -m "feat(workout-plans): update Exercise and WorkoutExercise models, add WorkoutPlan->workoutDays()"
```

---

## Task 5: New models — WorkoutDay and PlanAssignment

**Files:**
- Create: `modules/WorkoutPlans/app/Models/WorkoutDay.php`
- Create: `modules/WorkoutPlans/app/Models/PlanAssignment.php`

- [ ] **Step 1: Create WorkoutDay model**

```php
<?php

namespace Modules\WorkoutPlans\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\WorkoutPlans\Database\Factories\WorkoutDayFactory;

/**
 * @property int $id
 * @property int $workout_plan_id
 * @property int $day_number
 * @property string|null $name
 * @property int $order_index
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WorkoutDay extends Model
{
    /** @use HasFactory<WorkoutDayFactory> */
    use HasFactory;

    protected static function newFactory(): WorkoutDayFactory
    {
        return WorkoutDayFactory::new();
    }

    protected $fillable = [
        'workout_plan_id',
        'day_number',
        'name',
        'order_index',
    ];

    protected function casts(): array
    {
        return [
            'day_number' => 'integer',
            'order_index' => 'integer',
        ];
    }

    /** @return BelongsTo<WorkoutPlan, $this> */
    public function workoutPlan(): BelongsTo
    {
        return $this->belongsTo(WorkoutPlan::class);
    }

    /** @return HasMany<WorkoutExercise, $this> */
    public function workoutExercises(): HasMany
    {
        return $this->hasMany(WorkoutExercise::class)->orderBy('order_index');
    }
}
```

- [ ] **Step 2: Create PlanAssignment model**

```php
<?php

namespace Modules\WorkoutPlans\Models;

use App\Modules\Users\Models\Client;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $workout_plan_id
 * @property int $client_id
 * @property Carbon|null $active_from
 * @property Carbon $assigned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PlanAssignment extends Model
{
    protected $fillable = [
        'workout_plan_id',
        'client_id',
        'active_from',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'active_from' => 'date',
            'assigned_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<WorkoutPlan, $this> */
    public function workoutPlan(): BelongsTo
    {
        return $this->belongsTo(WorkoutPlan::class);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add modules/WorkoutPlans/app/Models/WorkoutDay.php modules/WorkoutPlans/app/Models/PlanAssignment.php
git commit -m "feat(workout-plans): add WorkoutDay and PlanAssignment models"
```

---

## Task 6: New models — WorkoutSession, SessionSet, PersonalRecord

**Files:**
- Create: `modules/WorkoutPlans/app/Models/WorkoutSession.php`
- Create: `modules/WorkoutPlans/app/Models/SessionSet.php`
- Create: `modules/WorkoutPlans/app/Models/PersonalRecord.php`

- [ ] **Step 1: Create WorkoutSession model**

```php
<?php

namespace Modules\WorkoutPlans\Models;

use App\Modules\Users\Models\Client;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\WorkoutPlans\Database\Factories\WorkoutSessionFactory;

/**
 * @property int $id
 * @property int $client_id
 * @property int|null $workout_plan_id
 * @property int|null $workout_day_id
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property float|null $total_volume_kg
 * @property int|null $duration_minutes
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WorkoutSession extends Model
{
    /** @use HasFactory<WorkoutSessionFactory> */
    use HasFactory;

    protected static function newFactory(): WorkoutSessionFactory
    {
        return WorkoutSessionFactory::new();
    }

    protected $fillable = [
        'client_id',
        'workout_plan_id',
        'workout_day_id',
        'started_at',
        'completed_at',
        'total_volume_kg',
        'duration_minutes',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_volume_kg' => 'float',
            'duration_minutes' => 'integer',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<WorkoutPlan, $this> */
    public function workoutPlan(): BelongsTo
    {
        return $this->belongsTo(WorkoutPlan::class);
    }

    /** @return BelongsTo<WorkoutDay, $this> */
    public function workoutDay(): BelongsTo
    {
        return $this->belongsTo(WorkoutDay::class);
    }

    /** @return HasMany<SessionSet, $this> */
    public function sessionSets(): HasMany
    {
        return $this->hasMany(SessionSet::class);
    }
}
```

- [ ] **Step 2: Create SessionSet model**

```php
<?php

namespace Modules\WorkoutPlans\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $workout_session_id
 * @property int $exercise_id
 * @property int $set_number
 * @property int|null $reps_done
 * @property float|null $weight_kg
 * @property int|null $rir_actual
 * @property bool $is_completed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SessionSet extends Model
{
    protected $fillable = [
        'workout_session_id',
        'exercise_id',
        'set_number',
        'reps_done',
        'weight_kg',
        'rir_actual',
        'is_completed',
    ];

    protected function casts(): array
    {
        return [
            'set_number' => 'integer',
            'reps_done' => 'integer',
            'weight_kg' => 'float',
            'rir_actual' => 'integer',
            'is_completed' => 'boolean',
        ];
    }

    /** @return BelongsTo<WorkoutSession, $this> */
    public function workoutSession(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class);
    }

    /** @return BelongsTo<Exercise, $this> */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
```

- [ ] **Step 3: Create PersonalRecord model**

```php
<?php

namespace Modules\WorkoutPlans\Models;

use App\Modules\Users\Models\Client;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $client_id
 * @property int $exercise_id
 * @property float $weight_kg
 * @property Carbon $recorded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PersonalRecord extends Model
{
    protected $fillable = [
        'client_id',
        'exercise_id',
        'weight_kg',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'float',
            'recorded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Exercise, $this> */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add modules/WorkoutPlans/app/Models/WorkoutSession.php modules/WorkoutPlans/app/Models/SessionSet.php modules/WorkoutPlans/app/Models/PersonalRecord.php
git commit -m "feat(workout-plans): add WorkoutSession, SessionSet, PersonalRecord models"
```

---

## Task 7: Factories

**Files:**
- Create: `modules/WorkoutPlans/database/factories/WorkoutDayFactory.php`
- Create: `modules/WorkoutPlans/database/factories/WorkoutSessionFactory.php`
- Modify: `modules/WorkoutPlans/database/factories/ExerciseFactory.php`

- [ ] **Step 1: Create WorkoutDayFactory**

```php
<?php

namespace Modules\WorkoutPlans\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\WorkoutPlans\Models\WorkoutDay;
use Modules\WorkoutPlans\Models\WorkoutPlan;

/**
 * @extends Factory<WorkoutDay>
 */
class WorkoutDayFactory extends Factory
{
    protected $model = WorkoutDay::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        static $order = 0;

        return [
            'workout_plan_id' => WorkoutPlan::factory(),
            'day_number' => fake()->numberBetween(1, 7),
            'name' => fake()->randomElement(['Upper body', 'Lower body', 'Push', 'Pull', 'Legs', 'Full body', 'Rest']),
            'order_index' => $order++,
        ];
    }
}
```

- [ ] **Step 2: Create WorkoutSessionFactory**

```php
<?php

namespace Modules\WorkoutPlans\Database\Factories;

use App\Modules\Users\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\WorkoutPlans\Models\WorkoutSession;

/**
 * @extends Factory<WorkoutSession>
 */
class WorkoutSessionFactory extends Factory
{
    protected $model = WorkoutSession::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-30 days', 'now');

        return [
            'client_id' => Client::factory(),
            'workout_plan_id' => null,
            'workout_day_id' => null,
            'started_at' => $startedAt,
            'completed_at' => null,
            'total_volume_kg' => null,
            'duration_minutes' => null,
            'notes' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(function () {
            return [
                'completed_at' => now(),
                'total_volume_kg' => fake()->randomFloat(2, 1000, 10000),
                'duration_minutes' => fake()->numberBetween(30, 90),
            ];
        });
    }
}
```

- [ ] **Step 3: Update ExerciseFactory — add primary_muscles and secondary_muscles**

In the `definition()` method, add after `muscle_groups`:

```php
'primary_muscles' => fake()->randomElements(['chest', 'back', 'shoulders', 'biceps', 'triceps', 'legs', 'glutes', 'core'], fake()->numberBetween(1, 2)),
'secondary_muscles' => fake()->randomElements(['calves', 'forearms', 'hamstrings', 'quads', 'abs'], fake()->numberBetween(0, 2)),
```

- [ ] **Step 4: Commit**

```bash
git add modules/WorkoutPlans/database/factories/
git commit -m "feat(workout-plans): add WorkoutDay and WorkoutSession factories, update ExerciseFactory"
```

---

## Task 8: ExerciseController — GET /exercises list

**Files:**
- Create: `modules/WorkoutPlans/app/Http/Resources/ExerciseResource.php`
- Create: `modules/WorkoutPlans/app/Http/Controllers/Api/V1/ExerciseController.php`
- Create: `modules/WorkoutPlans/tests/Feature/Api/ExerciseControllerTest.php`

- [ ] **Step 1: Create ExerciseResource**

```php
<?php

namespace Modules\WorkoutPlans\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExerciseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'category' => $this->category?->value,
            'difficulty' => $this->difficulty?->value,
            'primaryMuscles' => $this->primary_muscles ?? [],
            'secondaryMuscles' => $this->secondary_muscles ?? [],
            'muscleGroups' => $this->muscle_groups ?? [],
            'equipment' => $this->equipment ?? [],
            'videoUrl' => $this->video_url,
            'thumbnailUrl' => $this->thumbnail_url,
            'isCustom' => $this->coach_id !== null,
            'coachId' => $this->coach_id,
            'isActive' => $this->is_active,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
```

- [ ] **Step 2: Write failing test for GET /exercises**

```php
<?php

namespace Modules\WorkoutPlans\Tests\Feature\Api;

use App\Models\User;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\WorkoutPlans\Models\Exercise;
use Tests\TestCase;

class ExerciseControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Coach $coach;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->coach = Coach::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_authenticated_user_can_list_exercises(): void
    {
        $public = Exercise::factory()->count(3)->global()->create();
        $custom = Exercise::factory()->count(2)->create(['coach_id' => $this->coach->id]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/exercises');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'category', 'isCustom']]])
            ->assertJsonCount(5, 'data');
    }

    public function test_exercises_are_ordered_custom_first(): void
    {
        Exercise::factory()->count(2)->global()->create();
        $custom = Exercise::factory()->create(['coach_id' => $this->coach->id, 'name' => 'My Custom Exercise']);

        $response = $this->actingAs($this->user)->getJson('/api/v1/exercises');

        $response->assertOk();
        $this->assertEquals('My Custom Exercise', $response->json('data.0.name'));
    }

    public function test_exercises_can_be_filtered_by_search(): void
    {
        Exercise::factory()->create(['name' => 'Bench Press', 'coach_id' => null]);
        Exercise::factory()->create(['name' => 'Squat', 'coach_id' => null]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/exercises?search=bench');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertEquals('Bench Press', $response->json('data.0.name'));
    }

    public function test_exercises_can_be_filtered_by_type(): void
    {
        Exercise::factory()->strength()->create(['coach_id' => null]);
        Exercise::factory()->create(['category' => 'cardio', 'coach_id' => null]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/exercises?type=strength');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_exercises_are_paginated(): void
    {
        Exercise::factory()->count(25)->global()->create();

        $response = $this->actingAs($this->user)->getJson('/api/v1/exercises?limit=10');

        $response->assertOk()->assertJsonCount(10, 'data');
        $response->assertJsonStructure(['data', 'meta' => ['total', 'per_page', 'current_page']]);
    }

    public function test_unauthenticated_user_cannot_list_exercises(): void
    {
        $this->getJson('/api/v1/exercises')->assertUnauthorized();
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

```bash
php -d memory_limit=2048M artisan test --compact --filter=ExerciseControllerTest
```

Expected: FAIL — route not found.

- [ ] **Step 4: Create ExerciseController with index method**

```php
<?php

namespace Modules\WorkoutPlans\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\WorkoutPlans\Http\Resources\ExerciseResource;
use Modules\WorkoutPlans\Models\Exercise;

class ExerciseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $coach = $request->user()->coach;
        $coachId = $coach?->id;

        $query = Exercise::active()
            ->when($coachId, fn ($q) => $q->where(function ($q) use ($coachId) {
                $q->whereNull('coach_id')->orWhere('coach_id', $coachId);
            }), fn ($q) => $q->whereNull('coach_id'))
            ->when($request->input('search'), fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->when($request->input('type'), fn ($q, $type) => $q->where('category', $type))
            ->when($request->input('muscleGroup'), fn ($q, $muscle) => $q->where(function ($q) use ($muscle) {
                $q->whereJsonContains('primary_muscles', $muscle)
                  ->orWhereJsonContains('secondary_muscles', $muscle);
            }))
            ->orderByRaw($coachId ? "CASE WHEN coach_id = {$coachId} THEN 0 ELSE 1 END" : '1=1')
            ->orderBy('name');

        $limit = min((int) $request->input('limit', 20), 100);
        $exercises = $query->paginate($limit);

        return response()->json([
            'data' => ExerciseResource::collection($exercises->items()),
            'meta' => [
                'total' => $exercises->total(),
                'per_page' => $exercises->perPage(),
                'current_page' => $exercises->currentPage(),
                'last_page' => $exercises->lastPage(),
            ],
        ]);
    }
}
```

- [ ] **Step 5: Add route (stub — full routes in Task 17)**

In `modules/WorkoutPlans/routes/api.php`, add temporarily:

```php
<?php

use Illuminate\Support\Facades\Route;
use Modules\WorkoutPlans\Http\Controllers\Api\V1\ExerciseController;

Route::prefix('api/v1')->middleware(['jwt.auth', 'verified.email'])->group(function () {
    Route::get('exercises', [ExerciseController::class, 'index'])->name('workoutplans.api.exercises.index');
});
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
php -d memory_limit=2048M artisan test --compact --filter=ExerciseControllerTest
```

Expected: all 5 tests PASS.

- [ ] **Step 7: Commit**

```bash
git add modules/WorkoutPlans/app/Http/Resources/ExerciseResource.php modules/WorkoutPlans/app/Http/Controllers/Api/V1/ExerciseController.php modules/WorkoutPlans/tests/Feature/Api/ExerciseControllerTest.php modules/WorkoutPlans/routes/api.php
git commit -m "feat(workout-plans): add GET /exercises endpoint with filtering and pagination"
```

---

## Task 9: ExerciseController — POST/PUT/DELETE + requests

**Files:**
- Create: `modules/WorkoutPlans/app/Http/Requests/StoreExerciseRequest.php`
- Create: `modules/WorkoutPlans/app/Http/Requests/UpdateExerciseRequest.php`
- Modify: `modules/WorkoutPlans/app/Http/Controllers/Api/V1/ExerciseController.php`
- Modify: `modules/WorkoutPlans/tests/Feature/Api/ExerciseControllerTest.php`

- [ ] **Step 1: Write failing tests for store/update/delete**

Append to `ExerciseControllerTest`:

```php
    public function test_coach_can_create_custom_exercise(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/coaches/me/exercises', [
            'name' => 'Cable Fly',
            'primaryMuscles' => ['chest'],
            'secondaryMuscles' => ['shoulders'],
            'type' => 'strength',
            'instructions' => 'Keep elbows slightly bent.',
            'videoUrl' => 'https://youtube.com/watch?v=abc123',
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'Cable Fly');
        $this->assertDatabaseHas('exercises', ['name' => 'Cable Fly', 'coach_id' => $this->coach->id]);
    }

    public function test_coach_can_update_own_custom_exercise(): void
    {
        $exercise = Exercise::factory()->create(['coach_id' => $this->coach->id, 'name' => 'Old Name']);

        $response = $this->actingAs($this->user)->putJson("/api/v1/coaches/me/exercises/{$exercise->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'New Name');
    }

    public function test_coach_cannot_update_public_exercise(): void
    {
        $exercise = Exercise::factory()->global()->create();

        $this->actingAs($this->user)->putJson("/api/v1/coaches/me/exercises/{$exercise->id}", [
            'name' => 'Hacked',
        ])->assertForbidden();
    }

    public function test_coach_can_delete_own_custom_exercise(): void
    {
        $exercise = Exercise::factory()->create(['coach_id' => $this->coach->id]);

        $this->actingAs($this->user)->deleteJson("/api/v1/coaches/me/exercises/{$exercise->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('exercises', ['id' => $exercise->id]);
    }

    public function test_coach_cannot_delete_public_exercise(): void
    {
        $exercise = Exercise::factory()->global()->create();

        $this->actingAs($this->user)->deleteJson("/api/v1/coaches/me/exercises/{$exercise->id}")
            ->assertForbidden();
    }

    public function test_store_exercise_validates_required_name(): void
    {
        $this->actingAs($this->user)->postJson('/api/v1/coaches/me/exercises', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php -d memory_limit=2048M artisan test --compact --filter=ExerciseControllerTest
```

Expected: new tests FAIL — routes not defined.

- [ ] **Step 3: Create StoreExerciseRequest**

```php
<?php

namespace Modules\WorkoutPlans\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExerciseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'primaryMuscles' => ['nullable', 'array'],
            'primaryMuscles.*' => ['string', 'max:50'],
            'secondaryMuscles' => ['nullable', 'array'],
            'secondaryMuscles.*' => ['string', 'max:50'],
            'type' => ['nullable', 'string', 'max:50'],
            'videoUrl' => ['nullable', 'url', 'max:500'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
```

- [ ] **Step 4: Create UpdateExerciseRequest**

```php
<?php

namespace Modules\WorkoutPlans\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExerciseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'primaryMuscles' => ['sometimes', 'nullable', 'array'],
            'primaryMuscles.*' => ['string', 'max:50'],
            'secondaryMuscles' => ['sometimes', 'nullable', 'array'],
            'secondaryMuscles.*' => ['string', 'max:50'],
            'type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'videoUrl' => ['sometimes', 'nullable', 'url', 'max:500'],
            'instructions' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
```

- [ ] **Step 5: Add store/update/destroy to ExerciseController**

```php
use Modules\WorkoutPlans\Http\Requests\StoreExerciseRequest;
use Modules\WorkoutPlans\Http\Requests\UpdateExerciseRequest;

public function store(StoreExerciseRequest $request): JsonResponse
{
    $coach = $request->user()->coach;

    if (! $coach) {
        return response()->json(['message' => 'Coach profile not found.'], 403);
    }

    $exercise = Exercise::create([
        'coach_id' => $coach->id,
        'name' => $request->validated('name'),
        'description' => $request->validated('description'),
        'instructions' => $request->validated('instructions'),
        'category' => $request->validated('type'),
        'primary_muscles' => $request->validated('primaryMuscles', []),
        'secondary_muscles' => $request->validated('secondaryMuscles', []),
        'video_url' => $request->validated('videoUrl'),
        'is_active' => true,
    ]);

    return response()->json(['data' => new ExerciseResource($exercise)], 201);
}

public function update(UpdateExerciseRequest $request, Exercise $exercise): JsonResponse
{
    $coach = $request->user()->coach;

    if (! $coach || $exercise->coach_id !== $coach->id) {
        return response()->json(['message' => 'Non autorizzato.'], 403);
    }

    $exercise->update(array_filter([
        'name' => $request->validated('name'),
        'description' => $request->validated('description'),
        'instructions' => $request->validated('instructions'),
        'category' => $request->validated('type'),
        'primary_muscles' => $request->validated('primaryMuscles'),
        'secondary_muscles' => $request->validated('secondaryMuscles'),
        'video_url' => $request->validated('videoUrl'),
    ], fn ($v) => $v !== null));

    return response()->json(['data' => new ExerciseResource($exercise->fresh())]);
}

public function destroy(Request $request, Exercise $exercise): JsonResponse
{
    $coach = $request->user()->coach;

    if (! $coach || $exercise->coach_id !== $coach->id) {
        return response()->json(['message' => 'Non autorizzato.'], 403);
    }

    $exercise->delete();

    return response()->json(null, 204);
}
```

- [ ] **Step 6: Add routes for store/update/destroy in api.php**

```php
Route::prefix('coaches/me')->middleware('role:coach')->group(function () {
    Route::post('exercises', [ExerciseController::class, 'store'])->name('workoutplans.api.exercises.store');
    Route::put('exercises/{exercise}', [ExerciseController::class, 'update'])->name('workoutplans.api.exercises.update');
    Route::delete('exercises/{exercise}', [ExerciseController::class, 'destroy'])->name('workoutplans.api.exercises.destroy');
});
```

- [ ] **Step 7: Run all ExerciseController tests**

```bash
php -d memory_limit=2048M artisan test --compact --filter=ExerciseControllerTest
```

Expected: all tests PASS.

- [ ] **Step 8: Commit**

```bash
git add modules/WorkoutPlans/app/Http/Controllers/Api/V1/ExerciseController.php modules/WorkoutPlans/app/Http/Requests/ modules/WorkoutPlans/tests/Feature/Api/ExerciseControllerTest.php modules/WorkoutPlans/routes/api.php
git commit -m "feat(workout-plans): add POST/PUT/DELETE exercise endpoints for coaches"
```

---

## Task 10: WorkoutPlanController — store (transaction) + resources

**Files:**
- Create: `modules/WorkoutPlans/app/Http/Resources/WorkoutPlanResource.php`
- Create: `modules/WorkoutPlans/app/Http/Resources/WorkoutPlanDetailResource.php`
- Create: `modules/WorkoutPlans/app/Http/Resources/WorkoutDayResource.php`
- Create: `modules/WorkoutPlans/app/Http/Resources/WorkoutExerciseResource.php`
- Create: `modules/WorkoutPlans/app/Http/Requests/StoreWorkoutPlanRequest.php`
- Create: `modules/WorkoutPlans/app/Http/Controllers/Api/V1/WorkoutPlanController.php`
- Create: `modules/WorkoutPlans/tests/Feature/Api/WorkoutPlanControllerTest.php`

- [ ] **Step 1: Create resources**

```php
<?php
// modules/WorkoutPlans/app/Http/Resources/WorkoutExerciseResource.php

namespace Modules\WorkoutPlans\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutExerciseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exerciseId' => $this->exercise_id,
            'exercise' => $this->whenLoaded('exercise', fn () => new ExerciseResource($this->exercise)),
            'sets' => $this->sets,
            'repsTarget' => $this->reps_target,
            'weightKg' => $this->weight_kg,
            'rirTarget' => $this->rir_target,
            'restSeconds' => $this->rest_seconds,
            'notes' => $this->notes,
            'orderIndex' => $this->order_index,
        ];
    }
}
```

```php
<?php
// modules/WorkoutPlans/app/Http/Resources/WorkoutDayResource.php

namespace Modules\WorkoutPlans\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutDayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dayNumber' => $this->day_number,
            'name' => $this->name,
            'orderIndex' => $this->order_index,
            'exercises' => WorkoutExerciseResource::collection($this->whenLoaded('workoutExercises')),
        ];
    }
}
```

```php
<?php
// modules/WorkoutPlans/app/Http/Resources/WorkoutPlanResource.php

namespace Modules\WorkoutPlans\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'goal' => $this->goal?->value,
            'difficulty' => $this->difficulty?->value,
            'durationWeeks' => $this->duration_weeks,
            'sessionsPerWeek' => $this->sessions_per_week,
            'isTemplate' => $this->is_template,
            'isActive' => $this->is_active,
            'coachId' => $this->coach_id,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
```

```php
<?php
// modules/WorkoutPlans/app/Http/Resources/WorkoutPlanDetailResource.php

namespace Modules\WorkoutPlans\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutPlanDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'goal' => $this->goal?->value,
            'difficulty' => $this->difficulty?->value,
            'durationWeeks' => $this->duration_weeks,
            'sessionsPerWeek' => $this->sessions_per_week,
            'isTemplate' => $this->is_template,
            'isActive' => $this->is_active,
            'coachId' => $this->coach_id,
            'days' => WorkoutDayResource::collection($this->whenLoaded('workoutDays')),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
```

- [ ] **Step 2: Create StoreWorkoutPlanRequest**

```php
<?php

namespace Modules\WorkoutPlans\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkoutPlanRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'isTemplate' => ['boolean'],
            'goal' => ['nullable', 'string', 'max:50'],
            'difficulty' => ['nullable', 'string', 'max:50'],
            'durationWeeks' => ['nullable', 'integer', 'min:1', 'max:52'],
            'sessionsPerWeek' => ['nullable', 'integer', 'min:1', 'max:7'],
            'days' => ['nullable', 'array'],
            'days.*.dayNumber' => ['required_with:days', 'integer', 'min:1', 'max:31'],
            'days.*.name' => ['nullable', 'string', 'max:255'],
            'days.*.exercises' => ['nullable', 'array'],
            'days.*.exercises.*.exerciseId' => ['required_with:days.*.exercises', 'integer', 'exists:exercises,id'],
            'days.*.exercises.*.sets' => ['nullable', 'integer', 'min:1'],
            'days.*.exercises.*.repsTarget' => ['nullable', 'integer', 'min:1'],
            'days.*.exercises.*.weightKg' => ['nullable', 'numeric', 'min:0'],
            'days.*.exercises.*.rirTarget' => ['nullable', 'integer', 'min:0', 'max:10'],
            'days.*.exercises.*.restSeconds' => ['nullable', 'integer', 'min:0'],
            'days.*.exercises.*.notes' => ['nullable', 'string', 'max:1000'],
            'days.*.exercises.*.orderIndex' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
```

- [ ] **Step 3: Write failing tests for store**

```php
<?php

namespace Modules\WorkoutPlans\Tests\Feature\Api;

use App\Models\User;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\WorkoutPlans\Models\Exercise;
use Modules\WorkoutPlans\Models\WorkoutPlan;
use Tests\TestCase;

class WorkoutPlanControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Coach $coach;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->coach = Coach::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_coach_can_create_workout_plan_with_days_and_exercises(): void
    {
        $exercise = Exercise::factory()->global()->create();

        $response = $this->actingAs($this->user)->postJson('/api/v1/coaches/me/workout-plans', [
            'name' => 'My Strength Plan',
            'description' => 'A 4-week strength program.',
            'isTemplate' => false,
            'days' => [
                [
                    'dayNumber' => 1,
                    'name' => 'Push Day',
                    'exercises' => [
                        [
                            'exerciseId' => $exercise->id,
                            'sets' => 4,
                            'repsTarget' => 10,
                            'weightKg' => 80.0,
                            'rirTarget' => 2,
                            'restSeconds' => 90,
                            'orderIndex' => 0,
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('workout_plans', ['name' => 'My Strength Plan', 'coach_id' => $this->coach->id]);
        $this->assertDatabaseHas('workout_days', ['day_number' => 1, 'name' => 'Push Day']);
        $this->assertDatabaseHas('workout_exercises', ['exercise_id' => $exercise->id, 'sets' => 4, 'reps_target' => 10]);
    }

    public function test_store_validates_required_name(): void
    {
        $this->actingAs($this->user)->postJson('/api/v1/coaches/me/workout-plans', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_unauthenticated_cannot_create_plan(): void
    {
        $this->postJson('/api/v1/coaches/me/workout-plans', ['name' => 'Test'])
            ->assertUnauthorized();
    }
}
```

- [ ] **Step 4: Run test to verify it fails**

```bash
php -d memory_limit=2048M artisan test --compact --filter=WorkoutPlanControllerTest
```

Expected: FAIL — routes not found.

- [ ] **Step 5: Create WorkoutPlanController with store**

```php
<?php

namespace Modules\WorkoutPlans\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\WorkoutPlans\Http\Requests\StoreWorkoutPlanRequest;
use Modules\WorkoutPlans\Http\Resources\WorkoutPlanDetailResource;
use Modules\WorkoutPlans\Http\Resources\WorkoutPlanResource;
use Modules\WorkoutPlans\Models\WorkoutDay;
use Modules\WorkoutPlans\Models\WorkoutExercise;
use Modules\WorkoutPlans\Models\WorkoutPlan;

class WorkoutPlanController extends Controller
{
    public function store(StoreWorkoutPlanRequest $request): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach) {
            return response()->json(['message' => 'Coach profile not found.'], 403);
        }

        $plan = DB::transaction(function () use ($request, $coach) {
            $plan = WorkoutPlan::create([
                'coach_id' => $coach->id,
                'name' => $request->validated('name'),
                'description' => $request->validated('description'),
                'goal' => $request->validated('goal'),
                'difficulty' => $request->validated('difficulty'),
                'duration_weeks' => $request->validated('durationWeeks'),
                'sessions_per_week' => $request->validated('sessionsPerWeek'),
                'is_template' => $request->boolean('isTemplate', false),
                'is_active' => true,
            ]);

            foreach ($request->validated('days', []) as $dayIndex => $dayData) {
                $day = WorkoutDay::create([
                    'workout_plan_id' => $plan->id,
                    'day_number' => $dayData['dayNumber'],
                    'name' => $dayData['name'] ?? null,
                    'order_index' => $dayIndex,
                ]);

                foreach ($dayData['exercises'] ?? [] as $exData) {
                    WorkoutExercise::create([
                        'workout_plan_id' => $plan->id,
                        'workout_day_id' => $day->id,
                        'exercise_id' => $exData['exerciseId'],
                        'sets' => $exData['sets'] ?? null,
                        'reps_target' => $exData['repsTarget'] ?? null,
                        'weight_kg' => $exData['weightKg'] ?? null,
                        'rir_target' => $exData['rirTarget'] ?? null,
                        'rest_seconds' => $exData['restSeconds'] ?? null,
                        'notes' => $exData['notes'] ?? null,
                        'order_index' => $exData['orderIndex'] ?? 0,
                    ]);
                }
            }

            return $plan->load(['workoutDays.workoutExercises.exercise']);
        });

        return response()->json(['data' => new WorkoutPlanDetailResource($plan)], 201);
    }
}
```

- [ ] **Step 6: Add route stub in api.php**

```php
use Modules\WorkoutPlans\Http\Controllers\Api\V1\WorkoutPlanController;

// inside coaches/me group:
Route::post('workout-plans', [WorkoutPlanController::class, 'store'])->name('workoutplans.api.plans.store');
```

- [ ] **Step 7: Run tests**

```bash
php -d memory_limit=2048M artisan test --compact --filter=WorkoutPlanControllerTest
```

Expected: all tests PASS.

- [ ] **Step 8: Commit**

```bash
git add modules/WorkoutPlans/app/Http/Resources/ modules/WorkoutPlans/app/Http/Requests/StoreWorkoutPlanRequest.php modules/WorkoutPlans/app/Http/Controllers/Api/V1/WorkoutPlanController.php modules/WorkoutPlans/tests/Feature/Api/WorkoutPlanControllerTest.php modules/WorkoutPlans/routes/api.php
git commit -m "feat(workout-plans): add POST /coaches/me/workout-plans with transaction (days + exercises)"
```

---

## Task 11: WorkoutPlanController — index + show + update + destroy

**Files:**
- Modify: `modules/WorkoutPlans/app/Http/Controllers/Api/V1/WorkoutPlanController.php`
- Create: `modules/WorkoutPlans/app/Http/Requests/UpdateWorkoutPlanRequest.php`
- Modify: `modules/WorkoutPlans/tests/Feature/Api/WorkoutPlanControllerTest.php`

- [ ] **Step 1: Write failing tests**

Append to `WorkoutPlanControllerTest`:

```php
    public function test_coach_can_list_own_workout_plans(): void
    {
        WorkoutPlan::factory()->forCoach($this->coach)->count(3)->create();
        WorkoutPlan::factory()->count(2)->create(); // another coach

        $response = $this->actingAs($this->user)->getJson('/api/v1/coaches/me/workout-plans');

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_can_get_workout_plan_detail(): void
    {
        $plan = WorkoutPlan::factory()->forCoach($this->coach)->create();
        $day = \Modules\WorkoutPlans\Models\WorkoutDay::factory()->create(['workout_plan_id' => $plan->id]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/workout-plans/{$plan->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $plan->id)
            ->assertJsonStructure(['data' => ['id', 'name', 'days']]);
    }

    public function test_coach_can_update_own_workout_plan(): void
    {
        $plan = WorkoutPlan::factory()->forCoach($this->coach)->create(['name' => 'Old Name']);

        $response = $this->actingAs($this->user)->putJson("/api/v1/workout-plans/{$plan->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'New Name');
        $this->assertDatabaseHas('workout_plans', ['id' => $plan->id, 'name' => 'New Name']);
    }

    public function test_coach_can_soft_delete_own_workout_plan(): void
    {
        $plan = WorkoutPlan::factory()->forCoach($this->coach)->create();

        $this->actingAs($this->user)->deleteJson("/api/v1/workout-plans/{$plan->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('workout_plans', ['id' => $plan->id]);
    }

    public function test_coach_cannot_update_another_coachs_plan(): void
    {
        $otherPlan = WorkoutPlan::factory()->create();

        $this->actingAs($this->user)->putJson("/api/v1/workout-plans/{$otherPlan->id}", [
            'name' => 'Hacked',
        ])->assertForbidden();
    }
```

- [ ] **Step 2: Create UpdateWorkoutPlanRequest**

```php
<?php

namespace Modules\WorkoutPlans\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkoutPlanRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'isTemplate' => ['sometimes', 'boolean'],
            'isActive' => ['sometimes', 'boolean'],
            'goal' => ['sometimes', 'nullable', 'string', 'max:50'],
            'difficulty' => ['sometimes', 'nullable', 'string', 'max:50'],
            'durationWeeks' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:52'],
            'sessionsPerWeek' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:7'],
        ];
    }
}
```

- [ ] **Step 3: Add index, show, update, destroy to WorkoutPlanController**

```php
use Modules\WorkoutPlans\Http\Requests\UpdateWorkoutPlanRequest;

public function index(Request $request): JsonResponse
{
    $coach = $request->user()->coach;

    if (! $coach) {
        return response()->json(['message' => 'Coach profile not found.'], 403);
    }

    $plans = WorkoutPlan::forCoach($coach->id)
        ->active()
        ->latest()
        ->get();

    return response()->json(['data' => WorkoutPlanResource::collection($plans)]);
}

public function show(Request $request, WorkoutPlan $workoutPlan): JsonResponse
{
    $workoutPlan->load(['workoutDays.workoutExercises.exercise']);

    return response()->json(['data' => new WorkoutPlanDetailResource($workoutPlan)]);
}

public function update(UpdateWorkoutPlanRequest $request, WorkoutPlan $workoutPlan): JsonResponse
{
    $coach = $request->user()->coach;

    if (! $coach || $workoutPlan->coach_id !== $coach->id) {
        return response()->json(['message' => 'Non autorizzato.'], 403);
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
}

public function destroy(Request $request, WorkoutPlan $workoutPlan): JsonResponse
{
    $coach = $request->user()->coach;

    if (! $coach || $workoutPlan->coach_id !== $coach->id) {
        return response()->json(['message' => 'Non autorizzato.'], 403);
    }

    $workoutPlan->delete();

    return response()->json(null, 204);
}
```

- [ ] **Step 4: Add routes**

```php
// inside coaches/me group (role:coach):
Route::get('workout-plans', [WorkoutPlanController::class, 'index'])->name('workoutplans.api.plans.index');

// outside coaches/me, still auth:
Route::get('workout-plans/{workoutPlan}', [WorkoutPlanController::class, 'show'])->name('workoutplans.api.plans.show');
Route::put('workout-plans/{workoutPlan}', [WorkoutPlanController::class, 'update'])->name('workoutplans.api.plans.update');
Route::delete('workout-plans/{workoutPlan}', [WorkoutPlanController::class, 'destroy'])->name('workoutplans.api.plans.destroy');
```

- [ ] **Step 5: Run tests**

```bash
php -d memory_limit=2048M artisan test --compact --filter=WorkoutPlanControllerTest
```

Expected: all tests PASS.

- [ ] **Step 6: Commit**

```bash
git add modules/WorkoutPlans/app/Http/Controllers/Api/V1/WorkoutPlanController.php modules/WorkoutPlans/app/Http/Requests/UpdateWorkoutPlanRequest.php modules/WorkoutPlans/tests/Feature/Api/WorkoutPlanControllerTest.php modules/WorkoutPlans/routes/api.php
git commit -m "feat(workout-plans): add GET index/show, PUT update, DELETE destroy for workout plans"
```

---

## Task 12: WorkoutPlanController — duplicate + assign

**Files:**
- Create: `modules/WorkoutPlans/app/Http/Requests/AssignWorkoutPlanRequest.php`
- Modify: `modules/WorkoutPlans/app/Http/Controllers/Api/V1/WorkoutPlanController.php`
- Modify: `modules/WorkoutPlans/tests/Feature/Api/WorkoutPlanControllerTest.php`

- [ ] **Step 1: Write failing tests**

Append to `WorkoutPlanControllerTest`:

```php
    public function test_coach_can_duplicate_workout_plan(): void
    {
        $plan = WorkoutPlan::factory()->forCoach($this->coach)->create(['name' => 'My Plan', 'is_template' => false]);
        $day = \Modules\WorkoutPlans\Models\WorkoutDay::factory()->create(['workout_plan_id' => $plan->id]);
        $exercise = Exercise::factory()->global()->create();
        \Modules\WorkoutPlans\Models\WorkoutExercise::create([
            'workout_plan_id' => $plan->id,
            'workout_day_id' => $day->id,
            'exercise_id' => $exercise->id,
            'sets' => 3,
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/v1/workout-plans/{$plan->id}/duplicate");

        $response->assertCreated()->assertJsonPath('data.name', 'My Plan (copia)');
        $this->assertDatabaseCount('workout_plans', 2);
        $this->assertDatabaseCount('workout_days', 2);
        $this->assertDatabaseCount('workout_exercises', 2);
    }

    public function test_coach_can_assign_plan_to_clients(): void
    {
        $plan = WorkoutPlan::factory()->forCoach($this->coach)->create();
        $clients = \App\Modules\Users\Models\Client::factory()->count(2)->create();
        $this->coach->athletes()->attach($clients->pluck('id'));

        $response = $this->actingAs($this->user)->postJson("/api/v1/workout-plans/{$plan->id}/assign", [
            'clientIds' => $clients->pluck('id')->toArray(),
            'activeFrom' => '2026-07-01',
        ]);

        $response->assertOk();
        $this->assertDatabaseCount('plan_assignments', 2);
        $this->assertDatabaseHas('plan_assignments', [
            'workout_plan_id' => $plan->id,
            'client_id' => $clients->first()->id,
        ]);
    }

    public function test_assign_validates_client_ids(): void
    {
        $plan = WorkoutPlan::factory()->forCoach($this->coach)->create();

        $this->actingAs($this->user)->postJson("/api/v1/workout-plans/{$plan->id}/assign", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['clientIds']);
    }
```

- [ ] **Step 2: Create AssignWorkoutPlanRequest**

```php
<?php

namespace Modules\WorkoutPlans\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignWorkoutPlanRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'clientIds' => ['required', 'array', 'min:1'],
            'clientIds.*' => ['integer', 'exists:clients,id'],
            'activeFrom' => ['nullable', 'date'],
        ];
    }
}
```

- [ ] **Step 3: Add duplicate and assign to WorkoutPlanController**

```php
use Modules\WorkoutPlans\Http\Requests\AssignWorkoutPlanRequest;
use Modules\WorkoutPlans\Models\PlanAssignment;

public function duplicate(Request $request, WorkoutPlan $workoutPlan): JsonResponse
{
    $coach = $request->user()->coach;

    if (! $coach || $workoutPlan->coach_id !== $coach->id) {
        return response()->json(['message' => 'Non autorizzato.'], 403);
    }

    $copy = DB::transaction(function () use ($workoutPlan, $coach) {
        $copy = $workoutPlan->replicate();
        $copy->name = $workoutPlan->name.' (copia)';
        $copy->is_template = true;
        $copy->save();

        foreach ($workoutPlan->workoutDays()->with('workoutExercises')->get() as $day) {
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

        return $copy->load(['workoutDays.workoutExercises.exercise']);
    });

    return response()->json(['data' => new WorkoutPlanDetailResource($copy)], 201);
}

public function assign(AssignWorkoutPlanRequest $request, WorkoutPlan $workoutPlan): JsonResponse
{
    $coach = $request->user()->coach;

    if (! $coach || $workoutPlan->coach_id !== $coach->id) {
        return response()->json(['message' => 'Non autorizzato.'], 403);
    }

    $activeFrom = $request->validated('activeFrom')
        ? \Carbon\Carbon::parse($request->validated('activeFrom'))
        : null;

    foreach ($request->validated('clientIds') as $clientId) {
        PlanAssignment::updateOrCreate(
            ['workout_plan_id' => $workoutPlan->id, 'client_id' => $clientId],
            ['active_from' => $activeFrom, 'assigned_at' => now()]
        );
    }

    return response()->json(['message' => 'Piano assegnato con successo.', 'assigned' => count($request->validated('clientIds'))]);
}
```

- [ ] **Step 4: Add routes**

```php
Route::post('workout-plans/{workoutPlan}/duplicate', [WorkoutPlanController::class, 'duplicate'])->name('workoutplans.api.plans.duplicate');
Route::post('workout-plans/{workoutPlan}/assign', [WorkoutPlanController::class, 'assign'])->name('workoutplans.api.plans.assign');
```

- [ ] **Step 5: Run tests**

```bash
php -d memory_limit=2048M artisan test --compact --filter=WorkoutPlanControllerTest
```

Expected: all tests PASS.

- [ ] **Step 6: Commit**

```bash
git add modules/WorkoutPlans/app/Http/Controllers/Api/V1/WorkoutPlanController.php modules/WorkoutPlans/app/Http/Requests/AssignWorkoutPlanRequest.php modules/WorkoutPlans/tests/Feature/Api/WorkoutPlanControllerTest.php modules/WorkoutPlans/routes/api.php
git commit -m "feat(workout-plans): add duplicate and assign endpoints for workout plans"
```

---

## Task 13: CalculateAiProgressionJob (stub)

**Files:**
- Create: `modules/WorkoutPlans/app/Jobs/CalculateAiProgressionJob.php`

- [ ] **Step 1: Create stub job**

```php
<?php

namespace Modules\WorkoutPlans\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CalculateAiProgressionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $workoutSessionId,
        public readonly int $exerciseId,
    ) {}

    public function handle(): void
    {
        Log::info('TODO: requires AI progression service', [
            'workout_session_id' => $this->workoutSessionId,
            'exercise_id' => $this->exerciseId,
        ]);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add modules/WorkoutPlans/app/Jobs/CalculateAiProgressionJob.php
git commit -m "feat(workout-plans): add CalculateAiProgressionJob stub"
```

---

## Task 14: WorkoutSessionController — start + complete

**Files:**
- Create: `modules/WorkoutPlans/app/Http/Resources/WorkoutSessionResource.php`
- Create: `modules/WorkoutPlans/app/Http/Requests/StartWorkoutSessionRequest.php`
- Create: `modules/WorkoutPlans/app/Http/Requests/CompleteWorkoutSessionRequest.php`
- Create: `modules/WorkoutPlans/app/Http/Controllers/Api/V1/WorkoutSessionController.php`
- Create: `modules/WorkoutPlans/tests/Feature/Api/WorkoutSessionControllerTest.php`

- [ ] **Step 1: Create WorkoutSessionResource**

```php
<?php

namespace Modules\WorkoutPlans\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clientId' => $this->client_id,
            'workoutPlanId' => $this->workout_plan_id,
            'workoutDayId' => $this->workout_day_id,
            'startedAt' => $this->started_at?->toISOString(),
            'completedAt' => $this->completed_at?->toISOString(),
            'totalVolumeKg' => $this->total_volume_kg,
            'durationMinutes' => $this->duration_minutes,
            'notes' => $this->notes,
            'day' => $this->whenLoaded('workoutDay', fn () => new WorkoutDayResource($this->workoutDay)),
        ];
    }
}
```

- [ ] **Step 2: Create request classes**

```php
<?php
// modules/WorkoutPlans/app/Http/Requests/StartWorkoutSessionRequest.php

namespace Modules\WorkoutPlans\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartWorkoutSessionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'planId' => ['required', 'integer', 'exists:workout_plans,id'],
            'dayId' => ['required', 'integer', 'exists:workout_days,id'],
            'scheduledDate' => ['nullable', 'date'],
        ];
    }
}
```

```php
<?php
// modules/WorkoutPlans/app/Http/Requests/CompleteWorkoutSessionRequest.php

namespace Modules\WorkoutPlans\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteWorkoutSessionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'sets' => ['required', 'array'],
            'sets.*.exerciseId' => ['required', 'integer', 'exists:exercises,id'],
            'sets.*.setNumber' => ['required', 'integer', 'min:1'],
            'sets.*.repsDone' => ['nullable', 'integer', 'min:0'],
            'sets.*.weightKg' => ['nullable', 'numeric', 'min:0'],
            'sets.*.rirActual' => ['nullable', 'integer', 'min:0', 'max:10'],
            'sets.*.isCompleted' => ['boolean'],
            'durationMinutes' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
```

- [ ] **Step 3: Write failing tests**

```php
<?php

namespace Modules\WorkoutPlans\Tests\Feature\Api;

use App\Models\User;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\WorkoutPlans\Models\Exercise;
use Modules\WorkoutPlans\Models\PersonalRecord;
use Modules\WorkoutPlans\Models\WorkoutDay;
use Modules\WorkoutPlans\Models\WorkoutPlan;
use Modules\WorkoutPlans\Models\WorkoutSession;
use Tests\TestCase;

class WorkoutSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $coachUser;
    private Coach $coach;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coachUser = User::factory()->create(['email_verified_at' => now()]);
        $this->coach = Coach::factory()->create(['user_id' => $this->coachUser->id]);
        $this->client = Client::factory()->create();
        $this->coach->athletes()->attach($this->client->id);
    }

    public function test_can_start_workout_session(): void
    {
        $plan = WorkoutPlan::factory()->forCoach($this->coach)->create();
        $day = WorkoutDay::factory()->create(['workout_plan_id' => $plan->id]);

        $response = $this->actingAs($this->coachUser)->postJson("/api/v1/clients/{$this->client->id}/workout-sessions/start", [
            'planId' => $plan->id,
            'dayId' => $day->id,
            'scheduledDate' => '2026-07-01',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('workout_sessions', [
            'client_id' => $this->client->id,
            'workout_plan_id' => $plan->id,
            'workout_day_id' => $day->id,
        ]);
    }

    public function test_can_complete_workout_session_and_calculates_volume(): void
    {
        $plan = WorkoutPlan::factory()->forCoach($this->coach)->create();
        $day = WorkoutDay::factory()->create(['workout_plan_id' => $plan->id]);
        $session = WorkoutSession::factory()->create([
            'client_id' => $this->client->id,
            'workout_plan_id' => $plan->id,
            'workout_day_id' => $day->id,
        ]);
        $exercise = Exercise::factory()->global()->create();

        $response = $this->actingAs($this->coachUser)->postJson(
            "/api/v1/clients/{$this->client->id}/workout-sessions/{$session->id}/complete",
            [
                'sets' => [
                    ['exerciseId' => $exercise->id, 'setNumber' => 1, 'repsDone' => 10, 'weightKg' => 80.0, 'rirActual' => 2, 'isCompleted' => true],
                    ['exerciseId' => $exercise->id, 'setNumber' => 2, 'repsDone' => 8, 'weightKg' => 80.0, 'rirActual' => 1, 'isCompleted' => true],
                ],
                'durationMinutes' => 45,
                'notes' => 'Good session.',
            ]
        );

        $response->assertOk();
        $this->assertDatabaseHas('workout_sessions', [
            'id' => $session->id,
            'total_volume_kg' => 1440.0, // (10+8) * 80
            'duration_minutes' => 45,
        ]);
        $this->assertDatabaseCount('session_sets', 2);
    }

    public function test_completing_session_upserts_personal_record(): void
    {
        $plan = WorkoutPlan::factory()->forCoach($this->coach)->create();
        $day = WorkoutDay::factory()->create(['workout_plan_id' => $plan->id]);
        $session = WorkoutSession::factory()->create([
            'client_id' => $this->client->id,
            'workout_plan_id' => $plan->id,
            'workout_day_id' => $day->id,
        ]);
        $exercise = Exercise::factory()->global()->create();

        $this->actingAs($this->coachUser)->postJson(
            "/api/v1/clients/{$this->client->id}/workout-sessions/{$session->id}/complete",
            [
                'sets' => [
                    ['exerciseId' => $exercise->id, 'setNumber' => 1, 'repsDone' => 5, 'weightKg' => 100.0, 'rirActual' => 0, 'isCompleted' => true],
                ],
                'durationMinutes' => 60,
            ]
        );

        $this->assertDatabaseHas('personal_records', [
            'client_id' => $this->client->id,
            'exercise_id' => $exercise->id,
            'weight_kg' => 100.0,
        ]);
    }

    public function test_personal_record_not_updated_if_lower_weight(): void
    {
        $plan = WorkoutPlan::factory()->forCoach($this->coach)->create();
        $day = WorkoutDay::factory()->create(['workout_plan_id' => $plan->id]);
        $exercise = Exercise::factory()->global()->create();

        PersonalRecord::create([
            'client_id' => $this->client->id,
            'exercise_id' => $exercise->id,
            'weight_kg' => 120.0,
            'recorded_at' => now()->subMonth(),
        ]);

        $session = WorkoutSession::factory()->create([
            'client_id' => $this->client->id,
            'workout_plan_id' => $plan->id,
            'workout_day_id' => $day->id,
        ]);

        $this->actingAs($this->coachUser)->postJson(
            "/api/v1/clients/{$this->client->id}/workout-sessions/{$session->id}/complete",
            [
                'sets' => [
                    ['exerciseId' => $exercise->id, 'setNumber' => 1, 'repsDone' => 5, 'weightKg' => 100.0, 'rirActual' => 0, 'isCompleted' => true],
                ],
                'durationMinutes' => 60,
            ]
        );

        $this->assertDatabaseHas('personal_records', [
            'client_id' => $this->client->id,
            'exercise_id' => $exercise->id,
            'weight_kg' => 120.0,
        ]);
    }
}
```

- [ ] **Step 4: Run tests to verify they fail**

```bash
php -d memory_limit=2048M artisan test --compact --filter=WorkoutSessionControllerTest
```

Expected: FAIL — routes not found.

- [ ] **Step 5: Create WorkoutSessionController**

```php
<?php

namespace Modules\WorkoutPlans\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\WorkoutPlans\Http\Requests\CompleteWorkoutSessionRequest;
use Modules\WorkoutPlans\Http\Requests\StartWorkoutSessionRequest;
use Modules\WorkoutPlans\Http\Resources\WorkoutSessionResource;
use Modules\WorkoutPlans\Jobs\CalculateAiProgressionJob;
use Modules\WorkoutPlans\Models\PersonalRecord;
use Modules\WorkoutPlans\Models\SessionSet;
use Modules\WorkoutPlans\Models\WorkoutDay;
use Modules\WorkoutPlans\Models\WorkoutSession;

class WorkoutSessionController extends Controller
{
    public function start(StartWorkoutSessionRequest $request, Client $client): JsonResponse
    {
        $day = WorkoutDay::with('workoutExercises.exercise')->findOrFail($request->validated('dayId'));

        $session = WorkoutSession::create([
            'client_id' => $client->id,
            'workout_plan_id' => $request->validated('planId'),
            'workout_day_id' => $day->id,
            'started_at' => now(),
        ]);

        $session->setRelation('workoutDay', $day);

        return response()->json(['data' => new WorkoutSessionResource($session)], 201);
    }

    public function complete(CompleteWorkoutSessionRequest $request, Client $client, WorkoutSession $workoutSession): JsonResponse
    {
        if ($workoutSession->client_id !== $client->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $sets = $request->validated('sets');

        DB::transaction(function () use ($request, $workoutSession, $sets, $client) {
            $insertData = array_map(fn ($set) => [
                'workout_session_id' => $workoutSession->id,
                'exercise_id' => $set['exerciseId'],
                'set_number' => $set['setNumber'],
                'reps_done' => $set['repsDone'] ?? null,
                'weight_kg' => $set['weightKg'] ?? null,
                'rir_actual' => $set['rirActual'] ?? null,
                'is_completed' => $set['isCompleted'] ?? false,
                'created_at' => now(),
                'updated_at' => now(),
            ], $sets);

            SessionSet::insert($insertData);

            $totalVolume = collect($sets)
                ->filter(fn ($s) => ($s['isCompleted'] ?? false) && isset($s['weightKg'], $s['repsDone']))
                ->sum(fn ($s) => $s['weightKg'] * $s['repsDone']);

            $workoutSession->update([
                'completed_at' => now(),
                'total_volume_kg' => $totalVolume > 0 ? $totalVolume : null,
                'duration_minutes' => $request->validated('durationMinutes'),
                'notes' => $request->validated('notes'),
            ]);

            $exerciseMaxWeights = collect($sets)
                ->filter(fn ($s) => ($s['isCompleted'] ?? false) && isset($s['weightKg']))
                ->groupBy('exerciseId')
                ->map(fn ($group) => $group->max('weightKg'));

            foreach ($exerciseMaxWeights as $exerciseId => $maxWeight) {
                $existing = PersonalRecord::where('client_id', $client->id)
                    ->where('exercise_id', $exerciseId)
                    ->first();

                if (! $existing || $maxWeight > $existing->weight_kg) {
                    PersonalRecord::updateOrCreate(
                        ['client_id' => $client->id, 'exercise_id' => $exerciseId],
                        ['weight_kg' => $maxWeight, 'recorded_at' => now()]
                    );
                }

                CalculateAiProgressionJob::dispatch($workoutSession->id, $exerciseId);
            }
        });

        return response()->json(['data' => new WorkoutSessionResource($workoutSession->fresh())]);
    }
}
```

- [ ] **Step 6: Add routes (stub)**

```php
Route::prefix('clients/{client}/workout-sessions')->middleware(['jwt.auth', 'verified.email'])->group(function () {
    Route::post('start', [\Modules\WorkoutPlans\Http\Controllers\Api\V1\WorkoutSessionController::class, 'start'])->name('workoutplans.api.sessions.start');
    Route::post('{workoutSession}/complete', [\Modules\WorkoutPlans\Http\Controllers\Api\V1\WorkoutSessionController::class, 'complete'])->name('workoutplans.api.sessions.complete');
});
```

- [ ] **Step 7: Run all tests**

```bash
php -d memory_limit=2048M artisan test --compact --filter=WorkoutSessionControllerTest
```

Expected: all tests PASS.

- [ ] **Step 8: Commit**

```bash
git add modules/WorkoutPlans/app/Http/Controllers/Api/V1/WorkoutSessionController.php modules/WorkoutPlans/app/Http/Requests/StartWorkoutSessionRequest.php modules/WorkoutPlans/app/Http/Requests/CompleteWorkoutSessionRequest.php modules/WorkoutPlans/app/Http/Resources/WorkoutSessionResource.php modules/WorkoutPlans/tests/Feature/Api/WorkoutSessionControllerTest.php modules/WorkoutPlans/routes/api.php
git commit -m "feat(workout-plans): add start/complete workout session endpoints with volume calc and personal records"
```

---

## Task 15: Consolidate API routes

**Files:**
- Modify: `modules/WorkoutPlans/routes/api.php`

- [ ] **Step 1: Replace api.php with final consolidated routes**

```php
<?php

use Illuminate\Support\Facades\Route;
use Modules\WorkoutPlans\Http\Controllers\Api\V1\ExerciseController;
use Modules\WorkoutPlans\Http\Controllers\Api\V1\WorkoutPlanController;
use Modules\WorkoutPlans\Http\Controllers\Api\V1\WorkoutSessionController;

Route::prefix('api/v1')->middleware(['jwt.auth', 'verified.email'])->group(function () {

    // Exercise library — readable by any authenticated user
    Route::get('exercises', [ExerciseController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('workoutplans.api.exercises.index');

    // Coach-only exercise management
    Route::prefix('coaches/me')->middleware('role:coach')->group(function () {
        Route::post('exercises', [ExerciseController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('workoutplans.api.exercises.store');

        Route::put('exercises/{exercise}', [ExerciseController::class, 'update'])
            ->middleware('throttle:30,1')
            ->name('workoutplans.api.exercises.update');

        Route::delete('exercises/{exercise}', [ExerciseController::class, 'destroy'])
            ->middleware('throttle:20,1')
            ->name('workoutplans.api.exercises.destroy');

        // Workout plan management
        Route::post('workout-plans', [WorkoutPlanController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('workoutplans.api.plans.store');

        Route::get('workout-plans', [WorkoutPlanController::class, 'index'])
            ->middleware('throttle:60,1')
            ->name('workoutplans.api.plans.index');
    });

    // Workout plan detail + actions — coach-scoped, ownership checked in controller
    Route::get('workout-plans/{workoutPlan}', [WorkoutPlanController::class, 'show'])
        ->middleware('throttle:60,1')
        ->name('workoutplans.api.plans.show');

    Route::put('workout-plans/{workoutPlan}', [WorkoutPlanController::class, 'update'])
        ->middleware(['role:coach', 'throttle:30,1'])
        ->name('workoutplans.api.plans.update');

    Route::delete('workout-plans/{workoutPlan}', [WorkoutPlanController::class, 'destroy'])
        ->middleware(['role:coach', 'throttle:20,1'])
        ->name('workoutplans.api.plans.destroy');

    Route::post('workout-plans/{workoutPlan}/duplicate', [WorkoutPlanController::class, 'duplicate'])
        ->middleware(['role:coach', 'throttle:10,1'])
        ->name('workoutplans.api.plans.duplicate');

    Route::post('workout-plans/{workoutPlan}/assign', [WorkoutPlanController::class, 'assign'])
        ->middleware(['role:coach', 'throttle:20,1'])
        ->name('workoutplans.api.plans.assign');

    // Workout sessions
    Route::prefix('clients/{client}/workout-sessions')->group(function () {
        Route::post('start', [WorkoutSessionController::class, 'start'])
            ->middleware('throttle:30,1')
            ->name('workoutplans.api.sessions.start');

        Route::post('{workoutSession}/complete', [WorkoutSessionController::class, 'complete'])
            ->middleware('throttle:20,1')
            ->name('workoutplans.api.sessions.complete');
    });
});
```

- [ ] **Step 2: Run full module test suite**

```bash
php -d memory_limit=2048M artisan test --compact --testsuite=Modules --filter='^Modules\WorkoutPlans\Tests'
```

Expected: all tests PASS.

- [ ] **Step 3: Commit**

```bash
git add modules/WorkoutPlans/routes/api.php
git commit -m "feat(workout-plans): consolidate all API routes in api.php"
```

---

## Task 16: Filament — ExerciseForm primary/secondary muscles

**Files:**
- Modify: `modules/WorkoutPlans/app/Filament/Resources/Exercises/Schemas/ExerciseForm.php`
- Modify: `modules/WorkoutPlans/tests/Feature/ExerciseResourceTest.php`

- [ ] **Step 1: Write failing test for new fields**

Append to `ExerciseResourceTest`:

```php
    public function test_admin_can_create_exercise_with_primary_and_secondary_muscles(): void
    {
        Livewire::test(CreateExercise::class)
            ->fillForm([
                'name' => 'Incline Press',
                'category' => ExerciseCategory::Strength->value,
                'difficulty' => ExerciseDifficulty::Intermediate->value,
                'primary_muscles' => ['chest', 'shoulders'],
                'secondary_muscles' => ['triceps'],
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('exercises', ['name' => 'Incline Press']);
        $exercise = Exercise::where('name', 'Incline Press')->first();
        $this->assertContains('chest', $exercise->primary_muscles);
        $this->assertContains('triceps', $exercise->secondary_muscles);
    }
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php -d memory_limit=2048M artisan test --compact --filter=ExerciseResourceTest
```

Expected: FAIL — form fields not found.

- [ ] **Step 3: Update ExerciseForm — add primary_muscles and secondary_muscles TagsInput**

In the `Targeting` section, after the existing `muscle_groups` TagsInput, add:

```php
TagsInput::make('primary_muscles')
    ->label(__('Primary Muscles'))
    ->suggestions(['chest', 'back', 'shoulders', 'biceps', 'triceps', 'legs', 'glutes', 'core', 'calves', 'forearms', 'hamstrings', 'quads'])
    ->nullable()
    ->columnSpanFull(),

TagsInput::make('secondary_muscles')
    ->label(__('Secondary Muscles'))
    ->suggestions(['chest', 'back', 'shoulders', 'biceps', 'triceps', 'legs', 'glutes', 'core', 'calves', 'forearms', 'hamstrings', 'quads'])
    ->nullable()
    ->columnSpanFull(),
```

- [ ] **Step 4: Run tests**

```bash
php -d memory_limit=2048M artisan test --compact --filter=ExerciseResourceTest
```

Expected: all tests PASS.

- [ ] **Step 5: Commit**

```bash
git add modules/WorkoutPlans/app/Filament/Resources/Exercises/Schemas/ExerciseForm.php modules/WorkoutPlans/tests/Feature/ExerciseResourceTest.php
git commit -m "feat(workout-plans): add primary_muscles and secondary_muscles to Filament ExerciseForm"
```

---

## Task 17: Filament — WorkoutPlanForm nested days repeater

**Files:**
- Modify: `modules/WorkoutPlans/app/Filament/Resources/WorkoutPlans/Schemas/WorkoutPlanForm.php`
- Modify: `modules/WorkoutPlans/tests/Feature/WorkoutPlanResourceTest.php`

- [ ] **Step 1: Write failing test for nested days**

Append to `WorkoutPlanResourceTest`:

```php
    public function test_admin_can_create_workout_plan_with_workout_days(): void
    {
        $exercise = \Modules\WorkoutPlans\Models\Exercise::factory()->global()->create();

        Livewire::test(CreateWorkoutPlan::class)
            ->fillForm([
                'coach_id' => $this->coach->id,
                'name' => 'Push Pull Legs',
                'is_template' => false,
                'is_active' => true,
                'workoutDays' => [
                    [
                        'day_number' => 1,
                        'name' => 'Push',
                        'order_index' => 0,
                        'workoutExercises' => [
                            [
                                'exercise_id' => $exercise->id,
                                'sets' => 4,
                                'reps_target' => 8,
                                'rest_seconds' => 90,
                                'order_index' => 0,
                            ],
                        ],
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('workout_plans', ['name' => 'Push Pull Legs']);
        $this->assertDatabaseHas('workout_days', ['day_number' => 1, 'name' => 'Push']);
        $this->assertDatabaseHas('workout_exercises', ['exercise_id' => $exercise->id, 'sets' => 4]);
    }
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php -d memory_limit=2048M artisan test --compact --filter=WorkoutPlanResourceTest
```

- [ ] **Step 3: Replace WorkoutPlanForm Exercises section with nested days repeater**

Replace the entire `Section::make(__('Exercises'))` block with:

```php
Section::make(__('Days & Exercises'))
    ->schema([
        Repeater::make('workoutDays')
            ->relationship()
            ->label(__('Training Days'))
            ->schema([
                TextInput::make('day_number')
                    ->label(__('Day Number'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(31)
                    ->required(),

                TextInput::make('name')
                    ->label(__('Day Name'))
                    ->placeholder('e.g. Push Day, Upper Body...')
                    ->nullable(),

                TextInput::make('order_index')
                    ->label(__('Order'))
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->hidden(),

                Repeater::make('workoutExercises')
                    ->relationship()
                    ->label(__('Exercises'))
                    ->schema([
                        Select::make('exercise_id')
                            ->label(__('Exercise'))
                            ->options(fn () => Exercise::active()->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),

                        TextInput::make('sets')
                            ->label(__('Sets'))
                            ->numeric()->minValue(1)->nullable(),

                        TextInput::make('reps_target')
                            ->label(__('Reps Target'))
                            ->numeric()->minValue(1)->nullable(),

                        TextInput::make('weight_kg')
                            ->label(__('Weight (kg)'))
                            ->numeric()->minValue(0)->nullable(),

                        TextInput::make('rir_target')
                            ->label(__('RIR Target'))
                            ->numeric()->minValue(0)->maxValue(10)->nullable(),

                        TextInput::make('rest_seconds')
                            ->label(__('Rest (sec)'))
                            ->numeric()->minValue(0)->nullable(),

                        TextInput::make('order_index')
                            ->label(__('Order'))
                            ->numeric()->minValue(0)->default(0)->hidden(),

                        Textarea::make('notes')
                            ->label(__('Notes'))
                            ->nullable()->rows(2)->columnSpanFull(),
                    ])
                    ->addActionLabel(__('Add Exercise'))
                    ->defaultItems(0)
                    ->reorderable('order_index')
                    ->columns(4)
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => isset($state['exercise_id'])
                        ? Exercise::find($state['exercise_id'])?->name
                        : null
                    )
                    ->columnSpanFull(),
            ])
            ->addActionLabel(__('Add Day'))
            ->defaultItems(0)
            ->reorderable('order_index')
            ->collapsible()
            ->itemLabel(fn (array $state): string => isset($state['name']) && $state['name']
                ? "Day {$state['day_number']}: {$state['name']}"
                : "Day {$state['day_number']}"
            )
            ->columnSpanFull(),
    ])
    ->columnSpanFull(),
```

Also remove the `Repeater::make('workoutExercises')` import if it existed standalone, and ensure `Textarea` is imported.

- [ ] **Step 4: Run tests**

```bash
php -d memory_limit=2048M artisan test --compact --filter=WorkoutPlanResourceTest
```

Expected: all tests PASS.

- [ ] **Step 5: Commit**

```bash
git add modules/WorkoutPlans/app/Filament/Resources/WorkoutPlans/Schemas/WorkoutPlanForm.php modules/WorkoutPlans/tests/Feature/WorkoutPlanResourceTest.php
git commit -m "feat(workout-plans): replace flat exercise repeater with nested days→exercises in WorkoutPlanForm"
```

---

## Task 18: Filament — WorkoutSessionResource (read-only admin view)

**Files:**
- Create: `modules/WorkoutPlans/app/Filament/Resources/WorkoutSessions/WorkoutSessionResource.php`
- Create: `modules/WorkoutPlans/app/Filament/Resources/WorkoutSessions/Pages/ListWorkoutSessions.php`
- Create: `modules/WorkoutPlans/app/Filament/Resources/WorkoutSessions/Tables/WorkoutSessionsTable.php`
- Modify: `modules/WorkoutPlans/app/Filament/WorkoutPlansPlugin.php`

- [ ] **Step 1: Create WorkoutSessionResource**

```php
<?php

namespace Modules\WorkoutPlans\Filament\Resources\WorkoutSessions;

use Filament\Resources\Resource;
use Filament\Tables\Table;
use Modules\WorkoutPlans\Filament\Resources\WorkoutSessions\Pages\ListWorkoutSessions;
use Modules\WorkoutPlans\Filament\Resources\WorkoutSessions\Tables\WorkoutSessionsTable;
use Modules\WorkoutPlans\Models\WorkoutSession;

class WorkoutSessionResource extends Resource
{
    protected static ?string $model = WorkoutSession::class;

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return __('Workout Plans');
    }

    public static function getNavigationLabel(): string
    {
        return __('Sessions');
    }

    public static function table(Table $table): Table
    {
        return WorkoutSessionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkoutSessions::route('/'),
        ];
    }
}
```

- [ ] **Step 2: Create WorkoutSessionsTable**

```php
<?php

namespace Modules\WorkoutPlans\Filament\Resources\WorkoutSessions\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkoutSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client.user.name')
                    ->label(__('Client'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('workoutPlan.name')
                    ->label(__('Plan'))
                    ->default('—')
                    ->sortable(),

                TextColumn::make('workoutDay.name')
                    ->label(__('Day'))
                    ->default('—'),

                TextColumn::make('started_at')
                    ->label(__('Started'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('completed_at')
                    ->label(__('Completed'))
                    ->dateTime()
                    ->default('—')
                    ->sortable(),

                TextColumn::make('total_volume_kg')
                    ->label(__('Volume (kg)'))
                    ->numeric()
                    ->default('—')
                    ->sortable(),

                TextColumn::make('duration_minutes')
                    ->label(__('Duration (min)'))
                    ->default('—')
                    ->sortable(),
            ])
            ->defaultSort('started_at', 'desc');
    }
}
```

- [ ] **Step 3: Create ListWorkoutSessions page**

```php
<?php

namespace Modules\WorkoutPlans\Filament\Resources\WorkoutSessions\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\WorkoutPlans\Filament\Resources\WorkoutSessions\WorkoutSessionResource;

class ListWorkoutSessions extends ListRecords
{
    protected static string $resource = WorkoutSessionResource::class;
}
```

- [ ] **Step 4: Check WorkoutPlansPlugin and register the new resource**

Open `modules/WorkoutPlans/app/Filament/WorkoutPlansPlugin.php`. In the `register()` method (or `boot()`), ensure `WorkoutSessionResource` is registered. If the plugin uses auto-discovery, no change needed. If resources are explicitly registered, add:

```php
use Modules\WorkoutPlans\Filament\Resources\WorkoutSessions\WorkoutSessionResource;

// in boot():
$panel->resources([WorkoutSessionResource::class]);
```

Check the existing plugin file first to determine the correct pattern.

- [ ] **Step 5: Run full module test suite**

```bash
php -d memory_limit=2048M artisan test --compact --testsuite=Modules --filter='^Modules\WorkoutPlans\Tests'
```

Expected: all tests PASS. Check for any regressions.

- [ ] **Step 6: Run pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 7: Commit**

```bash
git add modules/WorkoutPlans/app/Filament/Resources/WorkoutSessions/ modules/WorkoutPlans/app/Filament/WorkoutPlansPlugin.php
git commit -m "feat(workout-plans): add read-only WorkoutSessionResource in Filament admin"
```

---

## Final Verification

- [ ] **Run full module tests**

```bash
php -d memory_limit=2048M artisan test --compact --testsuite=Modules --filter='^Modules\WorkoutPlans\Tests'
```

Expected: all tests green.

- [ ] **Run PHPStan**

```bash
composer analyse
```

Fix any level-5 errors before declaring done.

- [ ] **Check routes are registered**

```bash
php artisan route:list --path=api/v1/exercises
php artisan route:list --path=api/v1/workout-plans
php artisan route:list --path=api/v1/clients
```

Expected: all 12 API routes listed.
