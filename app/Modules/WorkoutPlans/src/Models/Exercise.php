<?php

namespace App\Modules\WorkoutPlans\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Exercise extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'coach_id',
        'name',
        'slug',
        'description',
        'muscle_group',
        'equipment',
        'difficulty',
        'video_url',
        'thumbnail_url',
        'tags',
        'is_public',
    ];

    protected $casts = [
        'tags'      => 'array',
        'is_public' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Exercise $exercise) {
            if (empty($exercise->slug)) {
                $exercise->slug = Str::slug($exercise->name);
            }
        });
    }

    public function workoutPlans(): BelongsToMany
    {
        return $this->belongsToMany(WorkoutPlan::class, 'workout_exercises')
            ->using(WorkoutExercise::class)
            ->withPivot(['order', 'sets', 'reps', 'rest_seconds', 'weight_kg', 'duration_seconds', 'distance_meters', 'notes', 'day_of_week'])
            ->withTimestamps();
    }
}
