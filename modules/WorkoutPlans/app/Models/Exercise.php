<?php

namespace Modules\WorkoutPlans\Models;

use App\Modules\Users\Models\Coach;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\WorkoutPlans\Database\Factories\ExerciseFactory;
use Modules\WorkoutPlans\Enums\ExerciseCategory;
use Modules\WorkoutPlans\Enums\ExerciseDifficulty;

/**
 * @property int $id
 * @property int|null $coach_id
 * @property string $name
 * @property string|null $description
 * @property string|null $instructions
 * @property ExerciseCategory $category
 * @property ExerciseDifficulty $difficulty
 * @property array<string>|null $muscle_groups
 * @property array<string>|null $equipment
 * @property array<string>|null $primary_muscles
 * @property array<string>|null $secondary_muscles
 * @property string|null $video_url
 * @property string|null $thumbnail_url
 * @property array<string, mixed>|null $metadata
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Exercise extends Model
{
    /** @use HasFactory<ExerciseFactory> */
    use HasFactory, SoftDeletes;

    protected static function newFactory(): ExerciseFactory
    {
        return ExerciseFactory::new();
    }

    protected $fillable = [
        'coach_id',
        'name',
        'description',
        'instructions',
        'category',
        'difficulty',
        'muscle_groups',
        'equipment',
        'primary_muscles',
        'secondary_muscles',
        'video_url',
        'thumbnail_url',
        'metadata',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'category' => ExerciseCategory::class,
            'difficulty' => ExerciseDifficulty::class,
            'muscle_groups' => 'array',
            'equipment' => 'array',
            'primary_muscles' => 'array',
            'secondary_muscles' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Coach, $this> */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }

    /** @return HasMany<WorkoutExercise, $this> */
    public function workoutExercises(): HasMany
    {
        return $this->hasMany(WorkoutExercise::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function global(Builder $query): void
    {
        $query->whereNull('coach_id');
    }

    #[Scope]
    protected function forCoach(Builder $query, int $coachId): void
    {
        $query->where(function (Builder $q) use ($coachId) {
            $q->whereNull('coach_id')->orWhere('coach_id', $coachId);
        });
    }
}
