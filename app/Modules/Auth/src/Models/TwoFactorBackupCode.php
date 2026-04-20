<?php

namespace App\Modules\Auth\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwoFactorBackupCode extends Model
{
    protected $fillable = ['user_id', 'code_hash', 'used_at'];

    protected $casts = ['used_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsed(): bool
    {
        return ! is_null($this->used_at);
    }
}
