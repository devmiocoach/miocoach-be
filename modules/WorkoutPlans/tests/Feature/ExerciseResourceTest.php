<?php

namespace Modules\WorkoutPlans\Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\WorkoutPlans\Enums\ExerciseCategory;
use Modules\WorkoutPlans\Enums\ExerciseDifficulty;
use Modules\WorkoutPlans\Filament\Resources\Exercises\Pages\CreateExercise;
use Modules\WorkoutPlans\Filament\Resources\Exercises\Pages\EditExercise;
use Modules\WorkoutPlans\Filament\Resources\Exercises\Pages\ListExercises;
use Modules\WorkoutPlans\Models\Exercise;
use Tests\TestCase;

class ExerciseResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->assignRole(Role::ADMIN);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
        $this->actingAs($this->admin);
    }

    public function test_admin_can_list_exercises(): void
    {
        $exercises = Exercise::factory()->count(3)->create();

        Livewire::test(ListExercises::class)
            ->assertCanSeeTableRecords($exercises);
    }

    public function test_admin_can_create_exercise(): void
    {
        Livewire::test(CreateExercise::class)
            ->fillForm([
                'name' => 'Bench Press',
                'category' => ExerciseCategory::Strength->value,
                'difficulty' => ExerciseDifficulty::Intermediate->value,
                'muscle_groups' => ['chest', 'triceps'],
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('exercises', [
            'name' => 'Bench Press',
            'category' => ExerciseCategory::Strength->value,
            'difficulty' => ExerciseDifficulty::Intermediate->value,
        ]);
    }

    public function test_create_exercise_validates_required_fields(): void
    {
        Livewire::test(CreateExercise::class)
            ->fillForm(['name' => null, 'category' => null, 'difficulty' => null])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'category' => 'required', 'difficulty' => 'required']);
    }

    public function test_admin_can_edit_exercise(): void
    {
        $exercise = Exercise::factory()->create(['name' => 'Old Name']);

        Livewire::test(EditExercise::class, ['record' => $exercise->id])
            ->fillForm(['name' => 'Updated Name'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('exercises', ['id' => $exercise->id, 'name' => 'Updated Name']);
    }

    public function test_admin_can_delete_exercise(): void
    {
        $exercise = Exercise::factory()->create();

        Livewire::test(EditExercise::class, ['record' => $exercise->id])
            ->callAction('delete')
            ->assertNotified();

        $this->assertSoftDeleted('exercises', ['id' => $exercise->id]);
    }

    public function test_global_exercises_visible_without_coach(): void
    {
        $global = Exercise::factory()->global()->create();

        Livewire::test(ListExercises::class)
            ->assertCanSeeTableRecords([$global]);
    }

    public function test_admin_can_create_exercise_with_primary_and_secondary_muscles(): void
    {
        Livewire::test(CreateExercise::class)
            ->fillForm([
                'name' => 'Incline Press',
                'category' => ExerciseCategory::Strength->value,
                'difficulty' => ExerciseDifficulty::Intermediate->value,
                'primary_muscles' => ['chest', 'shoulders'],
                'secondary_muscles' => ['triceps'],
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('exercises', ['name' => 'Incline Press']);
        $exercise = Exercise::where('name', 'Incline Press')->first();
        $this->assertContains('chest', $exercise->primary_muscles);
        $this->assertContains('triceps', $exercise->secondary_muscles);
    }
}
