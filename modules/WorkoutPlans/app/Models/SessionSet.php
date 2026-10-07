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
