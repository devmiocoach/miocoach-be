<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\WorkoutPlans\Enums\ExerciseCategory;
use Modules\WorkoutPlans\Enums\ExerciseDifficulty;
use Modules\WorkoutPlans\Enums\WorkoutGoal;
use Modules\WorkoutPlans\Models\Exercise;
use Modules\WorkoutPlans\Models\WorkoutDay;
use Modules\WorkoutPlans\Models\WorkoutExercise;
use Modules\WorkoutPlans\Models\WorkoutPlan;

/**
 * Fills a coach account with enough data to exercise the coach UI:
 * 15 owned exercises, 2 plans with days and prescriptions, 2 athletes.
 *
 * Idempotent: re-running updates the same records instead of duplicating them.
 * Target a specific coach with `DEMO_COACH_ID=<id>`, otherwise the oldest one is used.
 */
class DemoCoachDataSeeder extends Seeder
{
    /**
     * @var list<array{name: string, category: ExerciseCategory, difficulty: ExerciseDifficulty, primary: list<string>, equipment: list<string>}>
     */
    private const EXERCISES = [
        ['name' => 'Squat con bilanciere', 'category' => ExerciseCategory::Strength, 'difficulty' => ExerciseDifficulty::Intermediate, 'primary' => ['quadricipiti', 'glutei'], 'equipment' => ['bilanciere']],
        ['name' => 'Panca piana', 'category' => ExerciseCategory::Strength, 'difficulty' => ExerciseDifficulty::Intermediate, 'primary' => ['pettorali', 'tricipiti'], 'equipment' => ['bilanciere', 'panca']],
        ['name' => 'Stacco da terra', 'category' => ExerciseCategory::Strength, 'difficulty' => ExerciseDifficulty::Advanced, 'primary' => ['dorsali', 'femorali'], 'equipment' => ['bilanciere']],
        ['name' => 'Trazioni alla sbarra', 'category' => ExerciseCategory::Strength, 'difficulty' => ExerciseDifficulty::Intermediate, 'primary' => ['dorsali', 'bicipiti'], 'equipment' => ['sbarra']],
        ['name' => 'Military press', 'category' => ExerciseCategory::Strength, 'difficulty' => ExerciseDifficulty::Intermediate, 'primary' => ['spalle', 'tricipiti'], 'equipment' => ['bilanciere']],
        ['name' => 'Affondi con manubri', 'category' => ExerciseCategory::Strength, 'difficulty' => ExerciseDifficulty::Beginner, 'primary' => ['quadricipiti', 'glutei'], 'equipment' => ['manubri']],
        ['name' => 'Rematore con bilanciere', 'category' => ExerciseCategory::Strength, 'difficulty' => ExerciseDifficulty::Intermediate, 'primary' => ['dorsali'], 'equipment' => ['bilanciere']],
        ['name' => 'Leg press', 'category' => ExerciseCategory::Strength, 'difficulty' => ExerciseDifficulty::Beginner, 'primary' => ['quadricipiti'], 'equipment' => ['macchina']],
        ['name' => 'Curl con manubri', 'category' => ExerciseCategory::Strength, 'difficulty' => ExerciseDifficulty::Beginner, 'primary' => ['bicipiti'], 'equipment' => ['manubri']],
        ['name' => 'French press', 'category' => ExerciseCategory::Strength, 'difficulty' => ExerciseDifficulty::Beginner, 'primary' => ['tricipiti'], 'equipment' => ['bilanciere']],
        ['name' => 'Plank', 'category' => ExerciseCategory::Strength, 'difficulty' => ExerciseDifficulty::Beginner, 'primary' => ['core'], 'equipment' => ['corpo libero']],
        ['name' => 'Crunch inverso', 'category' => ExerciseCategory::Strength, 'difficulty' => ExerciseDifficulty::Beginner, 'primary' => ['core'], 'equipment' => ['corpo libero']],
        ['name' => 'Corsa su tapis roulant', 'category' => ExerciseCategory::Cardio, 'difficulty' => ExerciseDifficulty::Beginner, 'primary' => ['gambe'], 'equipment' => ['tapis roulant']],
        ['name' => 'Burpees', 'category' => ExerciseCategory::Cardio, 'difficulty' => ExerciseDifficulty::Intermediate, 'primary' => ['full body'], 'equipment' => ['corpo libero']],
        ['name' => 'Mobilità anche', 'category' => ExerciseCategory::Mobility, 'difficulty' => ExerciseDifficulty::Beginner, 'primary' => ['anche'], 'equipment' => ['corpo libero']],
    ];

    public function run(): void
    {
        $coachId = env('DEMO_COACH_ID');

        $coach = $coachId
            ? Coach::findOrFail((int) $coachId)
            : Coach::query()->oldest('id')->first();

        if (! $coach) {
            $this->command?->error('No coach found. Complete the coach onboarding first.');

            return;
        }

        $exercises = $this->seedExercises($coach);
        $this->seedPlans($coach, $exercises);
        $this->seedAthletes($coach);

        $this->command?->info("Demo data seeded for coach #{$coach->id}.");
    }

    /** @return Collection<int, Exercise> */
    private function seedExercises(Coach $coach): Collection
    {
        return collect(self::EXERCISES)->map(fn (array $row): Exercise => Exercise::updateOrCreate(
            ['coach_id' => $coach->id, 'name' => $row['name']],
            [
                'description' => $row['name'].' — esercizio dimostrativo.',
                'instructions' => 'Esegui il movimento con tecnica controllata su tutto il range.',
                'category' => $row['category'],
                'difficulty' => $row['difficulty'],
                'muscle_groups' => $row['primary'],
                'primary_muscles' => $row['primary'],
                'equipment' => $row['equipment'],
                'is_active' => true,
            ],
        ));
    }

    /** @param Collection<int, Exercise> $exercises */
    private function seedPlans(Coach $coach, Collection $exercises): void
    {
        $plans = [
            [
                'name' => 'Full Body Principianti',
                'goal' => WorkoutGoal::GeneralFitness,
                'difficulty' => ExerciseDifficulty::Beginner,
                'days' => ['Giorno A', 'Giorno B'],
            ],
            [
                'name' => 'Upper / Lower Ipertrofia',
                'goal' => WorkoutGoal::MuscleGain,
                'difficulty' => ExerciseDifficulty::Intermediate,
                'days' => ['Upper', 'Lower', 'Full Body'],
            ],
        ];

        foreach ($plans as $planIndex => $row) {
            $plan = WorkoutPlan::updateOrCreate(
                ['coach_id' => $coach->id, 'name' => $row['name']],
                [
                    'description' => $row['name'].' — piano dimostrativo.',
                    'goal' => $row['goal'],
                    'difficulty' => $row['difficulty'],
                    'duration_weeks' => 8,
                    'sessions_per_week' => count($row['days']),
                    'is_template' => $planIndex === 0,
                    'is_active' => true,
                ],
            );

            foreach ($row['days'] as $dayIndex => $dayName) {
                $day = WorkoutDay::updateOrCreate(
                    ['workout_plan_id' => $plan->id, 'day_number' => $dayIndex + 1],
                    ['name' => $dayName, 'order_index' => $dayIndex],
                );

                // Four distinct exercises per day, rotating through the library.
                foreach (range(0, 3) as $slot) {
                    $exercise = $exercises[($planIndex * 5 + $dayIndex * 4 + $slot) % $exercises->count()];

                    WorkoutExercise::updateOrCreate(
                        ['workout_day_id' => $day->id, 'order_index' => $slot],
                        [
                            'workout_plan_id' => $plan->id,
                            'exercise_id' => $exercise->id,
                            'day_number' => $dayIndex + 1,
                            'week_number' => 1,
                            'sets' => 4,
                            'reps_target' => 10,
                            'rir_target' => 2,
                            'rest_seconds' => 90,
                        ],
                    );
                }
            }
        }
    }

    private function seedAthletes(Coach $coach): void
    {
        foreach (['Marco Rossi', 'Giulia Bianchi'] as $name) {
            $email = Str::slug($name).'@miocoach.test';

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'type' => UserType::ATLETA->value,
                    'password' => bcrypt('secretsauce'),
                    'email_verified_at' => now(),
                ],
            );

            if (! $user->hasRole('user')) {
                $user->assignRole('user');
            }

            $client = Client::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'goals' => ['Ricomposizione corporea'],
                    'status' => 'active',
                    'joined_at' => now(),
                ],
            );

            $coach->athletes()->syncWithoutDetaching([
                $client->id => ['status' => 'active', 'started_at' => now()],
            ]);
        }
    }
}
