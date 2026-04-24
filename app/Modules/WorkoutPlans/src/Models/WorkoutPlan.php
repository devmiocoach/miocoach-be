<?php

namespace App\Modules\WorkoutPlans\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WorkoutPlan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'coach_id',
        'client_id',
        'title',
        'slug',
        'description',
        'goal',
        'duration_weeks',
        'sessions_per_week',
        'difficulty',
        'status',
        'is_template',
    ];

    protected $casts = [
        'is_template'       => 'boolean',
        'sessions_per_week' => 'integer',
        'duration_weeks'    => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (WorkoutPlan $plan) {
            if (empty($plan->slug)) {
                $plan->slug = Str::slug($plan->title) . '-' . Str::random(6);
            }
        });
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function exercises(): BelongsToMany
    {
        return $this->belongsToMany(Exercise::class, 'workout_exercises')
            ->using(WorkoutExercise::class)
            ->withPivot(['order', 'sets', 'reps', 'rest_seconds', 'weight_kg', 'duration_seconds', 'distance_meters', 'notes', 'day_of_week'])
            ->orderByPivot('order')
            ->withTimestamps();
    }

    public function workoutExercises(): HasMany
    {
        return $this->hasMany(WorkoutExercise::class)->orderBy('order');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeTemplates($query)
    {
        return $query->where('is_template', true);
    }
}
