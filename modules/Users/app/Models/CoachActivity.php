<?php

namespace App\Modules\Users\Models;

use App\Modules\Users\Enums\CoachActivityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CoachActivity extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'coach_id',
        'event_type',
        'subject_type',
        'subject_id',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => CoachActivityType::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
