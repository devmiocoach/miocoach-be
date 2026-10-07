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
