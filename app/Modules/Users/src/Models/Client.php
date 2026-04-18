<?php

namespace App\Modules\Users\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'coach_id',
        'anamnesi',
        'goals',
        'birth_date',
        'gender',
        'height_cm',
        'weight_kg',
        'status',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'anamnesi'   => 'encrypted',
            'goals'      => 'array',
            'birth_date' => 'date',
            'joined_at'  => 'datetime',
            'height_cm'  => 'decimal:1',
            'weight_kg'  => 'decimal:2',
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
}
