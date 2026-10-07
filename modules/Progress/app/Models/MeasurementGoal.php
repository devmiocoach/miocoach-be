<?php

namespace Modules\Progress\Models;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Progress\Database\Factories\MeasurementGoalFactory;
use Modules\Progress\Enums\MetricType;

/**
 * @property int $id
 * @property int $client_id
 * @property int $coach_id
 * @property MetricType $metric_type
 * @property float $target_value
 * @property Carbon|null $target_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MeasurementGoal extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'coach_id',
        'metric_type',
        'target_value',
        'target_date',
    ];

    protected function casts(): array
    {
        return [
            'metric_type' => MetricType::class,
            'target_value' => 'float',
            'target_date' => 'date',
        ];
    }

    protected static function newFactory(): MeasurementGoalFactory
    {
        return MeasurementGoalFactory::new();
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Coach, $this> */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }
}
