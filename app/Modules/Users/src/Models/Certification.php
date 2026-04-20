<?php

namespace App\Modules\Users\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certification extends Model
{
    protected $fillable = [
        'coach_id',
        'name',
        'issuer',
        'issued_at',
        'file_url',
        'storage_path',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at'   => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }
}
