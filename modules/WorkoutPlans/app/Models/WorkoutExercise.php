<?php

namespace Modules\WorkoutPlans\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\WorkoutPlans\Database\Factories\WorkoutExerciseFactory;

/**
 * @property int $id
 * @property int|null $workout_plan_id
 * @property int|null $workout_day_id
 * @property int $exercise_id
 * @property int|null $week_number
 * @property int|null $day_number
 * @property int $order_index
 * @property int|null $sets
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
    /** @use HasFactory<WorkoutExerciseFactory> */
    use HasFactory;

    protected static function newFactory(): WorkoutExerciseFactory
    {
        return WorkoutExerciseFactory::new();
    }

    protected $table = 'workout_exercises';

    protected $fillable = [
        'workout_plan_id',
        'workout_day_id',
        'exercise_id',
        'week_number',
        'day_number',
        'order_index',
        'sets',
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
            'order_index' => 'integer',
            'sets' => 'integer',
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
