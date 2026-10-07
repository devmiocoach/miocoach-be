<?php

namespace Modules\Checkins\Models;

use App\Modules\Users\Models\Client;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Checkins\Database\Factories\CheckinResponseFactory;

/**
 * @property int $id
 * @property int $checkin_form_id
 * @property int $client_id
 * @property array<int, array{questionId:string,value:mixed}> $answers
 * @property Carbon $submitted_at
 */
class CheckinResponse extends Model
{
    /** @use HasFactory<CheckinResponseFactory> */
    use HasFactory;

    protected static function newFactory(): CheckinResponseFactory
    {
        return CheckinResponseFactory::new();
    }

    protected $fillable = ['checkin_form_id', 'client_id', 'answers', 'submitted_at'];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CheckinForm, $this> */
    public function checkinForm(): BelongsTo
    {
        return $this->belongsTo(CheckinForm::class);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
