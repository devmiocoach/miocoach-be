<?php

namespace App\Modules\WorkoutPlans\Policies;

use App\Models\User;
use App\Modules\WorkoutPlans\Models\WorkoutPlan;

class WorkoutPlanPolicy
{
    /**
     * Admin bypassa tutte le policy.
     */
    public function before(User $user): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole(['coach', 'client']);
    }

    /**
     * Un coach può vedere i propri piani; un client può vedere i piani assegnati a lui.
     */
    public function view(User $user, WorkoutPlan $workoutPlan): bool
    {
        if ($user->hasRole('coach')) {
            return $user->coach?->id === $workoutPlan->coach_id;
        }

        if ($user->hasRole('client')) {
            return $user->client?->id === $workoutPlan->client_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('coach');
    }

    public function update(User $user, WorkoutPlan $workoutPlan): bool
    {
        return $user->hasRole('coach') && $user->coach?->id === $workoutPlan->coach_id;
    }

    public function delete(User $user, WorkoutPlan $workoutPlan): bool
    {
        return $user->hasRole('coach') && $user->coach?->id === $workoutPlan->coach_id;
    }

    public function restore(User $user, WorkoutPlan $workoutPlan): bool
    {
        return $user->hasRole('coach') && $user->coach?->id === $workoutPlan->coach_id;
    }

    public function forceDelete(User $user, WorkoutPlan $workoutPlan): bool
    {
        return false;
    }
}
