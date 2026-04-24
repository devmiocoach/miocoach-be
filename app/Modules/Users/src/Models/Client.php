<?php

namespace App\Modules\Users\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'goals',
        'birth_date',
        'gender',
        'height_cm',
        'weight_kg',
        'tags',
        'phone',
        'avatar_url',
        'subscription_expires_at',
        'subscription_sessions_remaining',
    ];

    protected function casts(): array
    {
        return [
            'goals'                           => 'array',
            'tags'                            => 'array',
            'birth_date'                      => 'date',
            'joined_at'                       => 'datetime',
            'subscription_expires_at'         => 'datetime',
            'height_cm'                       => 'decimal:1',
            'weight_kg'                       => 'decimal:2',
            'subscription_sessions_remaining' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class)->withDefault();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ClientNote::class)->latest('created_at');
    }

    public function anamnesis(): HasOne
    {
        return $this->hasOne(ClientAnamnesis::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ClientFile::class)->latest();
    }
}
