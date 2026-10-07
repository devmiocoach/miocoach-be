<?php

namespace App\Modules\Users\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachContactRequest extends Model
{
    public const TYPE_MESSAGE = 'message';

    public const TYPE_FREE_CONSULTATION = 'free_consultation';

    protected $fillable = ['message', 'status', 'type'];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
