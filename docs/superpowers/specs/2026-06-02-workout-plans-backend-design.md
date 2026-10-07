# WorkoutPlans Module — Full Backend Design

**Date:** 2026-06-02
**Branch:** workout-plan-module
**Scope:** API controllers, migrations, models, Filament updates

---

## Context

The WorkoutPlans module skeleton exists with 3 migrations and basic Filament resources. The API layer is entirely absent. This spec closes the gap between the skeleton and the required API surface.

---

## Schema Design

### exercises table — additive changes

Add columns via new migration (`add_muscles_to_exercises_table`):
- `primary_muscles` JSON nullable — replaces `muscle_groups` for primary targeting
- `secondary_muscles` JSON nullable — new: secondary targeting

Keep `category`, `difficulty`, `muscle_groups` (Filament admin still uses them; `muscle_groups` deprecated but not dropped to avoid breaking existing data).

API `type` param maps to `category` column.
API `muscleGroup` search param queries across `primary_muscles` + `secondary_muscles` JSON.

### workout_exercises table — additive changes

Add columns via new migration (`add_prescription_fields_to_workout_exercises_table`):
- `reps_target` smallint nullable
- `weight_kg` decimal(5,2) nullable
- `rir_target` tinyint nullable (Reps In Reserve)
- `order_index` smallint default 0

Keep `reps`, `duration_seconds`, `order` to avoid data loss on existing rows.

### New: workout_days table

```
id
workout_plan_id FK workout_plans → cascade delete
day_number tinyint unsigned
name varchar(255) nullable
order_index smallint default 0
timestamps
```

`workout_exercises` gains `workout_day_id` FK nullable → exercises can belong to a day. `workout_plan_id` on `workout_exercises` stays for backwards compat.

### New: plan_assignments table

```
id
workout_plan_id FK workout_plans → cascade delete
client_id FK clients → cascade delete
active_from date nullable
assigned_at timestamp
timestamps
UNIQUE(workout_plan_id, client_id)
```

### New: workout_sessions table

```
id
client_id FK clients → cascade delete
workout_plan_id FK workout_plans nullable → nullOnDelete
workout_day_id FK workout_days nullable → nullOnDelete
started_at timestamp
completed_at timestamp nullable
total_volume_kg decimal(10,2) nullable
duration_minutes smallint unsigned nullable
notes text nullable
timestamps
```

### New: session_sets table

```
id
workout_session_id FK workout_sessions → cascade delete
exercise_id FK exercises → cascade delete
set_number tinyint unsigned
reps_done smallint unsigned nullable
weight_kg decimal(5,2) nullable
rir_actual tinyint nullable
is_completed boolean default false
timestamps
```

### New: personal_records table

```
id
client_id FK clients → cascade delete
exercise_id FK exercises → cascade delete
weight_kg decimal(5,2)
recorded_at timestamp
timestamps
UNIQUE(client_id, exercise_id)
```

---

## API Layer

All routes under `/api/v1/` prefix, authenticated via sanctum + coach/client role middleware where applicable.

### Exercise endpoints

| Method | Path | Controller | Middleware |
|--------|------|-----------|-----------|
| GET | `/exercises` | ExerciseController@index | auth |
| POST | `/coaches/me/exercises` | ExerciseController@store | auth, role:coach |
| PUT | `/coaches/me/exercises/{exercise}` | ExerciseController@update | auth, role:coach |
| DELETE | `/coaches/me/exercises/{exercise}` | ExerciseController@destroy | auth, role:coach |

**GET /exercises:**
- Returns public (coach_id = null) + coach's own exercises
- Coach's custom shown first, then public
- Filters: `search` (name LIKE), `muscleGroup` (JSON contains in primary/secondary), `type` (= category)
- Pagination: `page`, `limit` (default 20, max 100)

**POST/PUT/DELETE:** coach can only modify their own custom exercises (policy check).

### WorkoutPlan endpoints

| Method | Path | Controller |
|--------|------|-----------|
| POST | `/coaches/me/workout-plans` | WorkoutPlanController@store |
| GET | `/coaches/me/workout-plans` | WorkoutPlanController@index |
| GET | `/workout-plans/{plan}` | WorkoutPlanController@show |
| PUT | `/workout-plans/{plan}` | WorkoutPlanController@update |
| DELETE | `/workout-plans/{plan}` | WorkoutPlanController@destroy |
| POST | `/workout-plans/{plan}/duplicate` | WorkoutPlanController@duplicate |
| POST | `/workout-plans/{plan}/assign` | WorkoutPlanController@assign |

**POST store body:**
```json
{
  "name": "string",
  "description": "string?",
  "isTemplate": false,
  "days": [{
    "dayNumber": 1,
    "name": "Upper body",
    "exercises": [{
      "exerciseId": 1,
      "sets": 4,
      "repsTarget": 10,
      "weightKg": 80.0,
      "rirTarget": 2,
      "restSeconds": 90,
      "notes": "string?",
      "orderIndex": 0
    }]
  }]
}
```

Store and update run inside DB transaction: plan → days → exercises.

**duplicate:** copies plan + all days + all exercises, appends " (copia)" to name, sets `is_template = true`.

**assign body:** `{ clientIds: [1,2,3], activeFrom: "2026-06-10" }` — upserts PlanAssignment per client. Notification dispatch stubbed (depends on Notifications module).

**destroy:** soft delete only.

### WorkoutSession endpoints

| Method | Path | Controller |
|--------|------|-----------|
| POST | `/clients/{client}/workout-sessions/start` | WorkoutSessionController@start |
| POST | `/clients/{client}/workout-sessions/{session}/complete` | WorkoutSessionController@complete |

**start body:** `{ planId, dayId, scheduledDate }` → creates WorkoutSession, returns session + preloaded exercises from the day.

**complete body:**
```json
{
  "sets": [{ "exerciseId", "setNumber", "repsDone", "weightKg", "rirActual", "isCompleted" }],
  "durationMinutes": 45,
  "notes": "optional"
}
```

**complete transaction:**
1. Bulk insert SessionSets
2. Calculate `totalVolumeKg = Σ(weightKg × repsDone)` for completed sets
3. Update WorkoutSession (completedAt, totalVolumeKg, durationMinutes, notes)
4. For each exercise: upsert PersonalRecord if `weightKg > current record`
5. Dispatch `CalculateAiProgressionJob` stub per exercise

---

## Models

New models: `WorkoutDay`, `PlanAssignment`, `WorkoutSession`, `SessionSet`, `PersonalRecord`.

Updated models: `Exercise` (add primary_muscles, secondary_muscles), `WorkoutPlan` (add days() HasMany), `WorkoutExercise` (add new prescription fields).

All new models use strict typing, `$fillable`, `casts()` method, and PHPDoc `@property` blocks.

---

## Filament Updates

### ExerciseForm
Add to Targeting section:
- `TagsInput::make('primary_muscles')` with standard muscle suggestions
- `TagsInput::make('secondary_muscles')` with same suggestions

### WorkoutPlanForm
Replace flat exercise Repeater with nested structure:
- Outer `Repeater::make('workoutDays')` — relationship, fields: dayNumber, name
- Inner `Repeater` for exercises inside each day — fields: exercise_id, sets, reps_target, weight_kg, rir_target, rest_seconds, notes, order_index

### New Filament resource: WorkoutSessionResource
Admin view of sessions: client, plan, day, started_at, completed_at, total_volume_kg, duration_minutes. Read-only table (no create/edit in admin).

---

## Out of Scope (tracked in MODULES_TODO.md)
- Video upload to S3 + ffmpeg thumbnail
- FCM push notifications (assign + personal record)
- CalculateAiProgressionJob (stub only)
