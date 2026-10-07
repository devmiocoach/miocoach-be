<?php

namespace Modules\CoachCommerce\Models;

use App\Modules\Users\Models\Coach;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CoachCommerce\Database\Factories\SubscriptionPlanFactory;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'coach_id',
        'name',
        'description',
        'price_monthly',
        'price_yearly',
        'sessions_per_month',
        'features',
        'is_active',
        'stripe_price_id',
    ];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'sessions_per_month' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function newFactory(): SubscriptionPlanFactory
    {
        return SubscriptionPlanFactory::new();
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }
}
