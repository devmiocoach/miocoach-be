<?php

namespace Modules\Bookings\Models;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Bookings\Database\Factories\BookingFactory;
use Modules\Bookings\Enums\BookingCancelledBy;
use Modules\Bookings\Enums\BookingServiceType;
use Modules\Bookings\Enums\BookingSource;
use Modules\Bookings\Enums\BookingStatus;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'coach_id',
        'client_id',
        'package_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'service_type',
        'starts_at',
        'ends_at',
        'duration_minutes',
        'status',
        'notes',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by',
        'price',
        'source',
        'cancellation_token',
        'cancellation_token_expires_at',
        'google_calendar_event_id',
        'recurrence_rule',
        'recurrence_parent_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancellation_token_expires_at' => 'datetime',
            'status' => BookingStatus::class,
            'service_type' => BookingServiceType::class,
            'source' => BookingSource::class,
            'cancelled_by' => BookingCancelledBy::class,
            'duration_minutes' => 'integer',
            'price' => 'float',
        ];
    }

    protected static function newFactory(): BookingFactory
    {
        return BookingFactory::new();
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isForGuest(): bool
    {
        return $this->client_id === null && $this->guest_email !== null;
    }

    public function scopeConfirmed($query): void
    {
        $query->where('status', BookingStatus::Confirmed->value);
    }

    public function scopeForCoach($query, int $coachId): void
    {
        $query->where('coach_id', $coachId);
    }

    public function scopeUpcoming($query): void
    {
        $query->where('starts_at', '>', now());
    }
}
