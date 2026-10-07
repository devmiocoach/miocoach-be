<?php

namespace Modules\WorkoutPlans\Policies;

use App\Enums\Role;
use App\Models\User;
use Modules\WorkoutPlans\Models\Exercise;

class ExercisePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole(Role::ADMIN) ? true : null;
    }

    public function update(User $user, Exercise $exercise): bool
    {
        return $this->ownsCustom($user, $exercise);
    }

    public function delete(User $user, Exercise $exercise): bool
    {
        return $this->ownsCustom($user, $exercise);
    }

    /** Global exercises (coach_id === null) are read-only for coaches. */
    private function ownsCustom(User $user, Exercise $exercise): bool
    {
        return $exercise->coach_id !== null
            && $user->coach !== null
            && $exercise->coach_id === $user->coach->id;
    }
}
