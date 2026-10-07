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
