<?php

namespace App\Modules\Users\Models;

use App\Models\User;
use App\Modules\Users\Database\Factories\SpecializationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Specialization extends Model
{
    use HasFactory;

    /** @use HasFactory<SpecializationFactory> */
    protected static function newFactory(): SpecializationFactory
    {
        return SpecializationFactory::new();
    }

    protected $fillable = [
        'name',
        'slug',
        'is_custom',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_custom' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function coaches(): BelongsToMany
    {
        return $this->belongsToMany(Coach::class, 'coach_specialization');
    }
}
