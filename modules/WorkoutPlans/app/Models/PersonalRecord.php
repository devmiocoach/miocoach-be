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
