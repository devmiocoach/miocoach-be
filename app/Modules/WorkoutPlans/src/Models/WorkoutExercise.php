<?php

namespace App\Modules\WorkoutPlans\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class WorkoutExercise extends Pivot
{
    public $incrementing = true;

    protected $fillable = [
        'workout_plan_id',
        'exercise_id',
        'order',
        'sets',
        'reps',
        'rest_seconds',
        'weight_kg',
        'duration_seconds',
        'distance_meters',
        'notes',
        'day_of_week',
    ];

    protected $casts = [
        'order'     => 'integer',
        'sets'      => 'integer',
        'weight_kg' => 'decimal:2',
    ];

    public function workoutPlan(): BelongsTo
    {
        return $this->belongsTo(WorkoutPlan::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
