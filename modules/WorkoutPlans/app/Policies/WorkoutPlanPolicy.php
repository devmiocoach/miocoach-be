<?php

namespace Modules\WorkoutPlans\Policies;

use App\Enums\Role;
use App\Models\User;
use Modules\WorkoutPlans\Models\PlanAssignment;
use Modules\WorkoutPlans\Models\WorkoutPlan;

class WorkoutPlanPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole(Role::ADMIN) ? true : null;
    }

    public function view(User $user, WorkoutPlan $plan): bool
    {
        if ($this->owns($user, $plan)) {
            return true;
        }

        return $user->client !== null && PlanAssignment::query()
            ->where('workout_plan_id', $plan->id)
            ->where('client_id', $user->client->id)
            ->exists();
    }

    public function update(User $user, WorkoutPlan $plan): bool
    {
        return $this->owns($user, $plan);
    }

    public function delete(User $user, WorkoutPlan $plan): bool
    {
        return $this->owns($user, $plan);
    }

    public function duplicate(User $user, WorkoutPlan $plan): bool
    {
        return $this->owns($user, $plan);
    }

    private function owns(User $user, WorkoutPlan $plan): bool
    {
        return $user->coach !== null && $plan->coach_id === $user->coach->id;
    }
}
