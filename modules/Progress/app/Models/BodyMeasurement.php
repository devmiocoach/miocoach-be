<?php

namespace Modules\Progress\Models;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Progress\Database\Factories\BodyMeasurementFactory;
use Modules\Progress\Enums\MetricType;

/**
 * @property int $id
 * @property int $client_id
 * @property int $coach_id
 * @property MetricType $metric_type
 * @property float $value
 * @property Carbon $measured_at
 * @property string|null $notes
 * @property string|null $photo_storage_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BodyMeasurement extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'coach_id',
        'metric_type',
        'value',
        'measured_at',
        'notes',
        'photo_storage_key',
    ];

    protected function casts(): array
    {
        return [
            'metric_type' => MetricType::class,
            'value' => 'float',
            'measured_at' => 'datetime',
        ];
    }

    protected static function newFactory(): BodyMeasurementFactory
    {
        return BodyMeasurementFactory::new();
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

    /** @param Builder<self> $query */
    public function scopeForClient(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }

    /** @param Builder<self> $query */
    public function scopeOfType(Builder $query, MetricType $type): Builder
    {
        return $query->where('metric_type', $type->value);
    }

    /** @param Builder<self> $query */
    public function scopeInRange(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereBetween('measured_at', [$from, $to]);
    }

    /** @param Builder<self> $query */
    public function scopeForCoach(Builder $query, int $coachId): Builder
    {
        return $query->where('coach_id', $coachId);
    }
}
