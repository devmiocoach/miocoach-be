<?php

namespace Modules\WorkoutPlans\Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Users\Models\Coach;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\WorkoutPlans\Enums\ExerciseDifficulty;
use Modules\WorkoutPlans\Enums\WorkoutGoal;
use Modules\WorkoutPlans\Filament\Resources\WorkoutPlans\Pages\CreateWorkoutPlan;
use Modules\WorkoutPlans\Filament\Resources\WorkoutPlans\Pages\EditWorkoutPlan;
use Modules\WorkoutPlans\Filament\Resources\WorkoutPlans\Pages\ListWorkoutPlans;
use Modules\WorkoutPlans\Models\Exercise;
use Modules\WorkoutPlans\Models\WorkoutPlan;
use Tests\TestCase;

class WorkoutPlanResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Coach $coach;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->assignRole(Role::ADMIN);
        $this->coach = Coach::factory()->create();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
        $this->actingAs($this->admin);
    }

    public function test_admin_can_list_workout_plans(): void
    {
        $plans = WorkoutPlan::factory()->forCoach($this->coach)->count(3)->create();

        $this->actingAs($this->admin);

        Livewire::test(ListWorkoutPlans::class)
            ->assertCanSeeTableRecords($plans);
    }

    public function test_admin_can_create_workout_plan(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateWorkoutPlan::class)
            ->fillForm([
                'coach_id' => $this->coach->id,
                'name' => 'Strength Program 8 Weeks',
                'goal' => WorkoutGoal::Strength->value,
                'difficulty' => ExerciseDifficulty::Intermediate->value,
                'duration_weeks' => 8,
                'sessions_per_week' => 3,
                'is_template' => false,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('workout_plans', [
            'name' => 'Strength Program 8 Weeks',
            'goal' => WorkoutGoal::Strength->value,
            'duration_weeks' => 8,
        ]);
    }

    public function test_create_workout_plan_validates_required_fields(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateWorkoutPlan::class)
            ->fillForm(['name' => null, 'coach_id' => null])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'coach_id' => 'required']);
    }

    public function test_admin_can_edit_workout_plan(): void
    {
        $plan = WorkoutPlan::factory()->forCoach($this->coach)->create(['name' => 'Original']);

        $this->actingAs($this->admin);

        Livewire::test(EditWorkoutPlan::class, ['record' => $plan->id])
            ->fillForm(['name' => 'Updated Plan'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('workout_plans', ['id' => $plan->id, 'name' => 'Updated Plan']);
    }

    public function test_admin_can_delete_workout_plan(): void
    {
        $plan = WorkoutPlan::factory()->forCoach($this->coach)->create();

        $this->actingAs($this->admin);

        Livewire::test(EditWorkoutPlan::class, ['record' => $plan->id])
            ->callAction('delete')
            ->assertNotified();

        $this->assertSoftDeleted('workout_plans', ['id' => $plan->id]);
    }

    public function test_template_plans_are_visible(): void
    {
        $template = WorkoutPlan::factory()->forCoach($this->coach)->template()->create();

        $this->actingAs($this->admin);

        Livewire::test(ListWorkoutPlans::class)
            ->assertCanSeeTableRecords([$template]);
    }

    public function test_admin_can_create_workout_plan_with_workout_days(): void
    {
        $exercise = Exercise::factory()->global()->create();

        Livewire::test(CreateWorkoutPlan::class)
            ->fillForm([
                'coach_id' => $this->coach->id,
                'name' => 'Push Pull Legs',
                'is_template' => false,
                'is_active' => true,
                'workoutDays' => [
                    [
                        'day_number' => 1,
                        'name' => 'Push',
                        'order_index' => 0,
                        'workoutExercises' => [
                            [
                                'exercise_id' => $exercise->id,
                                'sets' => 4,
                                'reps_target' => 8,
                                'rest_seconds' => 90,
                                'order_index' => 0,
                            ],
                        ],
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('workout_plans', ['name' => 'Push Pull Legs']);
        $this->assertDatabaseHas('workout_days', ['day_number' => 1, 'name' => 'Push']);
        $this->assertDatabaseHas('workout_exercises', ['exercise_id' => $exercise->id, 'sets' => 4]);
    }
}
