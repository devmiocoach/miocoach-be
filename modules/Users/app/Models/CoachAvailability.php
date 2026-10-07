<?php

namespace App\Modules\Users\Models;

use App\Modules\Users\Database\Factories\CoachAvailabilityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachAvailability extends Model
{
    /** @use HasFactory<CoachAvailabilityFactory> */
    use HasFactory;

    protected static function newFactory(): CoachAvailabilityFactory
    {
        return CoachAvailabilityFactory::new();
    }

    protected $fillable = [
        'coach_id',
        'day_of_week',
        'start_time',
        'end_time',
        'duration_minutes',
        'is_recurring',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'duration_minutes' => 'integer',
            'is_recurring' => 'boolean',
        ];
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }
}
