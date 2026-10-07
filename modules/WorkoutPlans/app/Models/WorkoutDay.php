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
