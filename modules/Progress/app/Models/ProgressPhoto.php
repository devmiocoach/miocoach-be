<?php

namespace Modules\Progress\Models;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Progress\Database\Factories\ProgressPhotoFactory;
use Modules\Progress\Enums\PhotoPose;

/**
 * @property int $id
 * @property int $client_id
 * @property int $coach_id
 * @property string $storage_key
 * @property string|null $thumbnail_key
 * @property PhotoPose|null $pose
 * @property Carbon $taken_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ProgressPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'coach_id',
        'storage_key',
        'thumbnail_key',
        'pose',
        'taken_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'pose' => PhotoPose::class,
            'taken_at' => 'date',
        ];
    }

    protected static function newFactory(): ProgressPhotoFactory
    {
        return ProgressPhotoFactory::new();
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Coach, $this> */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }
}
