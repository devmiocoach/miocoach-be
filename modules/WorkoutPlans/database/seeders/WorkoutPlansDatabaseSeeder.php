<?php

namespace Modules\WorkoutPlans\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\WorkoutPlans\Enums\ExerciseCategory;
use Modules\WorkoutPlans\Enums\ExerciseDifficulty;
use Modules\WorkoutPlans\Models\Exercise;

class WorkoutPlansDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $exercises = [
            [
                'name' => 'Barbell Back Squat',
                'description' => 'Compound lower-body lift performed with a barbell across the upper back.',
                'instructions' => 'Brace your core, descend until your hips drop below your knees, then drive back up through the heels.',
                'category' => ExerciseCategory::Strength,
                'difficulty' => ExerciseDifficulty::Intermediate,
                'muscle_groups' => ['legs', 'glutes', 'core'],
                'equipment' => ['barbell'],
            ],
            [
                'name' => 'Barbell Bench Press',
                'description' => 'Primary horizontal pressing movement for the chest.',
                'instructions' => 'Lower the bar to mid-chest with control, then press it back to full lockout.',
                'category' => ExerciseCategory::Strength,
                'difficulty' => ExerciseDifficulty::Intermediate,
                'muscle_groups' => ['chest', 'triceps', 'shoulders'],
                'equipment' => ['barbell', 'bench'],
            ],
            [
                'name' => 'Conventional Deadlift',
                'description' => 'Full-body hip-hinge lift pulling a barbell from the floor.',
                'instructions' => 'Keep a neutral spine, push the floor away, and lock out hips and knees together.',
                'category' => ExerciseCategory::Strength,
                'difficulty' => ExerciseDifficulty::Advanced,
                'muscle_groups' => ['back', 'glutes', 'hamstrings'],
                'equipment' => ['barbell'],
            ],
            [
                'name' => 'Pull-up',
                'description' => 'Vertical pulling bodyweight exercise for back and biceps.',
                'instructions' => 'Pull your chin over the bar with a controlled tempo, then lower under control.',
                'category' => ExerciseCategory::Strength,
                'difficulty' => ExerciseDifficulty::Intermediate,
                'muscle_groups' => ['back', 'biceps'],
                'equipment' => ['pull_up_bar', 'bodyweight'],
            ],
            [
                'name' => 'Push-up',
                'description' => 'Bodyweight horizontal press scalable for any level.',
                'instructions' => 'Keep a straight line from head to heels, lower your chest to the floor, then press up.',
                'category' => ExerciseCategory::Strength,
                'difficulty' => ExerciseDifficulty::Beginner,
                'muscle_groups' => ['chest', 'triceps', 'core'],
                'equipment' => ['bodyweight'],
            ],
            [
                'name' => 'Dumbbell Shoulder Press',
                'description' => 'Overhead pressing movement for the shoulders.',
                'instructions' => 'Press the dumbbells overhead until your arms lock out, then lower to ear height.',
                'category' => ExerciseCategory::Strength,
                'difficulty' => ExerciseDifficulty::Beginner,
                'muscle_groups' => ['shoulders', 'triceps'],
                'equipment' => ['dumbbells'],
            ],
            [
                'name' => 'Walking Lunge',
                'description' => 'Unilateral lower-body exercise that builds strength and balance.',
                'instructions' => 'Step forward into a lunge, keep the front knee over the ankle, then alternate legs.',
                'category' => ExerciseCategory::Strength,
                'difficulty' => ExerciseDifficulty::Beginner,
                'muscle_groups' => ['legs', 'glutes'],
                'equipment' => ['bodyweight', 'dumbbells'],
            ],
            [
                'name' => 'Plank',
                'description' => 'Isometric core hold for trunk stability.',
                'instructions' => 'Hold a straight line on your forearms, brace the core, and breathe steadily.',
                'category' => ExerciseCategory::Strength,
                'difficulty' => ExerciseDifficulty::Beginner,
                'muscle_groups' => ['core'],
                'equipment' => ['bodyweight'],
            ],
            [
                'name' => 'Treadmill Intervals',
                'description' => 'High-intensity interval running to improve conditioning.',
                'instructions' => 'Alternate hard running efforts with easy recovery jogs for the prescribed rounds.',
                'category' => ExerciseCategory::Hiit,
                'difficulty' => ExerciseDifficulty::Intermediate,
                'muscle_groups' => ['legs', 'core'],
                'equipment' => ['machine'],
            ],
            [
                'name' => 'Steady-State Cycling',
                'description' => 'Low-impact cardio for aerobic endurance.',
                'instructions' => 'Hold a steady, sustainable pace at a moderate resistance for the full duration.',
                'category' => ExerciseCategory::Cardio,
                'difficulty' => ExerciseDifficulty::Beginner,
                'muscle_groups' => ['legs'],
                'equipment' => ['machine'],
            ],
            [
                'name' => 'Jumping Jacks',
                'description' => 'Full-body warm-up to raise the heart rate.',
                'instructions' => 'Jump while spreading arms and legs out and in with a rhythmic tempo.',
                'category' => ExerciseCategory::Warmup,
                'difficulty' => ExerciseDifficulty::Beginner,
                'muscle_groups' => ['legs', 'shoulders'],
                'equipment' => ['bodyweight'],
            ],
            [
                'name' => 'Hamstring Stretch',
                'description' => 'Static stretch to improve posterior chain flexibility.',
                'instructions' => 'Hinge forward over an extended leg and hold the stretch without bouncing.',
                'category' => ExerciseCategory::Stretching,
                'difficulty' => ExerciseDifficulty::Beginner,
                'muscle_groups' => ['hamstrings'],
                'equipment' => ['bodyweight'],
            ],
        ];

        foreach ($exercises as $exercise) {
            Exercise::updateOrCreate(
                ['name' => $exercise['name'], 'coach_id' => null],
                [...$exercise, 'is_active' => true],
            );
        }
    }
}
