<?php

namespace App\Modules\Users\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coach extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'slug',
        'bio',
        'description',
        'specialization',
        'certifications',
        'hourly_rate',
        'city',
        'is_verified',
        'is_visible',
        'social_links',
    ];

    protected function casts(): array
    {
        return [
            'certifications' => 'array',
            'social_links'   => 'array',
            'is_verified'    => 'boolean',
            'is_visible'     => 'boolean',
            'hourly_rate'    => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
