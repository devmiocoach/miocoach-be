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
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\WorkoutPlans\Database\Factories\WorkoutPlanFactory;
use Modules\WorkoutPlans\Enums\ExerciseDifficulty;
use Modules\WorkoutPlans\Enums\WorkoutGoal;

/**
 * @property int $id
 * @property int $coach_id
 * @property string $name
 * @property string|null $description
 * @property WorkoutGoal|null $goal
 * @property ExerciseDifficulty|null $difficulty
 * @property int|null $duration_weeks
 * @property int|null $sessions_per_week
 * @property bool $is_template
 * @property bool $is_active
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class WorkoutPlan extends Model
{
    /** @use HasFactory<WorkoutPlanFactory> */
    use HasFactory, SoftDeletes;

    protected static function newFactory(): WorkoutPlanFactory
    {
        return WorkoutPlanFactory::new();
    }

    protected $fillable = [
        'coach_id',
        'name',
        'description',
        'goal',
        'difficulty',
        'duration_weeks',
        'sessions_per_week',
        'is_template',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'goal' => WorkoutGoal::class,
            'difficulty' => ExerciseDifficulty::class,
            'duration_weeks' => 'integer',
            'sessions_per_week' => 'integer',
            'is_template' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
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
        return $this->hasMany(WorkoutExercise::class)
            ->orderBy('week_number')
            ->orderBy('day_number')
            ->orderBy('order_index');
    }

    /** @return HasMany<WorkoutDay, $this> */
    public function workoutDays(): HasMany
    {
        return $this->hasMany(WorkoutDay::class)->orderBy('order_index');
    }

    /** @return HasManyThrough<WorkoutExercise, WorkoutDay, $this> */
    public function allWorkoutExercises(): HasManyThrough
    {
        return $this->hasManyThrough(WorkoutExercise::class, WorkoutDay::class);
    }

    /** @return HasMany<PlanAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(PlanAssignment::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function templates(Builder $query): void
    {
        $query->where('is_template', true);
    }

    #[Scope]
    protected function forCoach(Builder $query, int $coachId): void
    {
        $query->where('coach_id', $coachId);
    }
}
