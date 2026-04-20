<?php

namespace App\Modules\Users\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachAvailability extends Model
{
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
            'day_of_week'      => 'integer',
            'duration_minutes' => 'integer',
            'is_recurring'     => 'boolean',
        ];
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }
}
