<?php

namespace Modules\Checkins\Models;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Checkins\Database\Factories\CheckinFormFactory;
use Modules\Checkins\Enums\CheckinFrequency;

/**
 * @property int $id
 * @property int $coach_id
 * @property string $title
 * @property CheckinFrequency $frequency
 * @property string $send_time
 * @property array<int, array{id:string,text:string,type:string,required:bool,order:int}> $questions
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CheckinForm extends Model
{
    /** @use HasFactory<CheckinFormFactory> */
    use HasFactory;

    protected static function newFactory(): CheckinFormFactory
    {
        return CheckinFormFactory::new();
    }

    protected $fillable = ['coach_id', 'title', 'frequency', 'send_time', 'questions', 'is_active'];

    protected function casts(): array
    {
        return [
            'frequency' => CheckinFrequency::class,
            'questions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @return BelongsTo<Coach, $this> */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }

    /** @return BelongsToMany<Client, $this> */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'checkin_form_client')
            ->withPivot('assigned_at')
            ->withTimestamps();
    }

    /** @return HasMany<CheckinResponse, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(CheckinResponse::class);
    }
}
