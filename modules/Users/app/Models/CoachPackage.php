<?php

namespace App\Modules\Users\Models;

use App\Modules\Users\Database\Factories\CoachPackageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachPackage extends Model
{
    /** @use HasFactory<CoachPackageFactory> */
    use HasFactory;

    protected static function newFactory(): CoachPackageFactory
    {
        return CoachPackageFactory::new();
    }

    protected $fillable = [
        'coach_id',
        'name',
        'description',
        'sessions_count',
        'price_cents',
        'validity_days',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sessions_count' => 'integer',
            'price_cents' => 'integer',
            'validity_days' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }

    public function getPriceInEurosAttribute(): float
    {
        return $this->price_cents / 100;
    }

    public function getSavingsCentsAttribute(?int $singleSessionPriceCents): int
    {
        if ($singleSessionPriceCents === null) {
            return 0;
        }

        return max(0, ($singleSessionPriceCents * $this->sessions_count) - $this->price_cents);
    }
}
