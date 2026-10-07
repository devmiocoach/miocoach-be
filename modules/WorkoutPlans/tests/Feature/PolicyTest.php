<?php

namespace Modules\WorkoutPlans\Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\WorkoutPlans\Models\Exercise;
use Modules\WorkoutPlans\Models\WorkoutPlan;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    private function makeCoach(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole(Role::COACH);
        $coach = Coach::factory()->create(['user_id' => $user->id]);

        return [$user, $coach];
    }

    public function test_coach_can_update_own_plan(): void
    {
        [$user, $coach] = $this->makeCoach();
        $plan = WorkoutPlan::factory()->create(['coach_id' => $coach->id]);

        $this->assertTrue($user->can('update', $plan));
    }

    public function test_coach_cannot_update_another_coachs_plan(): void
    {
        [$userA] = $this->makeCoach();
        [, $coachB] = $this->makeCoach();
        $plan = WorkoutPlan::factory()->create(['coach_id' => $coachB->id]);

        $this->assertFalse($userA->can('update', $plan));
    }

    public function test_coach_can_update_own_custom_exercise(): void
    {
        [$user, $coach] = $this->makeCoach();
        $exercise = Exercise::factory()->create(['coach_id' => $coach->id]);

        $this->assertTrue($user->can('update', $exercise));
    }

    public function test_coach_cannot_update_a_global_exercise(): void
    {
        [$user] = $this->makeCoach();
        $exercise = Exercise::factory()->global()->create();

        $this->assertFalse($user->can('update', $exercise));
    }

    public function test_admin_can_update_a_global_exercise(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole(Role::ADMIN);
        $exercise = Exercise::factory()->global()->create();

        $this->assertTrue($admin->can('update', $exercise));
    }

    public function test_admin_can_update_any_plan(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole(Role::ADMIN);
        [, $coach] = $this->makeCoach();
        $plan = WorkoutPlan::factory()->create(['coach_id' => $coach->id]);

        $this->assertTrue($admin->can('update', $plan));
    }
}
