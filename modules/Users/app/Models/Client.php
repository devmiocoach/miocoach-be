<?php

namespace App\Modules\Users\Models;

use App\Models\User;
use App\Modules\Users\Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, SoftDeletes;

    protected static function newFactory(): ClientFactory
    {
        return ClientFactory::new();
    }

    protected static function booted(): void
    {
        // DB cascade fires only on hard delete. On soft delete, detach pivot rows
        // so coaches don't see ghost athletes in their active lists.
        static::deleting(function (Client $client) {
            if ($client->isForceDeleting()) {
                return;
            }

            $client->coaches()->detach();
        });
    }

    protected $fillable = [
        'goals',
        'birth_date',
        'gender',
        'height_cm',
        'weight_kg',
        'tags',
        'phone',
        'avatar_url',
        'objective',
        'fitness_level',
        'subscription_expires_at',
        'subscription_sessions_remaining',
        'timezone',
    ];

    protected function casts(): array
    {
        return [
            'goals' => 'array',
            'tags' => 'array',
            'birth_date' => 'date',
            'joined_at' => 'datetime',
            'subscription_expires_at' => 'datetime',
            'height_cm' => 'decimal:1',
            'weight_kg' => 'decimal:2',
            'subscription_sessions_remaining' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coaches(): BelongsToMany
    {
        return $this->belongsToMany(Coach::class, 'athlete_coach', 'athlete_id', 'coach_id')
            ->withPivot(['status', 'started_at', 'ended_at'])
            ->withTimestamps();
    }

    public function activeCoaches(): BelongsToMany
    {
        return $this->coaches()->wherePivot('status', 'active');
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
