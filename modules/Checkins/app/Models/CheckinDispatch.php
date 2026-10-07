<?php

namespace Modules\Checkins\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Idempotency marker: one row per (form, client, period) reminder actually sent.
 * The unique index on those three columns is the source of truth — this model
 * exists so the dispatch command can rely on a UniqueConstraintViolationException
 * to detect a duplicate even under concurrent runs.
 *
 * @property int $id
 * @property int $checkin_form_id
 * @property int $client_id
 * @property string $period_key
 * @property Carbon $dispatched_at
 */
class CheckinDispatch extends Model
{
    protected $fillable = ['checkin_form_id', 'client_id', 'period_key', 'dispatched_at'];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
        ];
    }
}
