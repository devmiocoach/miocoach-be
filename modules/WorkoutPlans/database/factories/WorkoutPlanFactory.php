<?php

namespace Modules\WorkoutPlans\Database\Factories;

use App\Modules\Users\Models\Coach;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\WorkoutPlans\Enums\ExerciseDifficulty;
use Modules\WorkoutPlans\Enums\WorkoutGoal;
use Modules\WorkoutPlans\Models\WorkoutPlan;

/**
 * @extends Factory<WorkoutPlan>
 */
class WorkoutPlanFactory extends Factory
{
    protected $model = WorkoutPlan::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'coach_id' => Coach::factory(),
            'name' => fake()->words(3, true).' Plan',
            'description' => fake()->paragraph(),
            'goal' => fake()->randomElement(WorkoutGoal::cases())->value,
            'difficulty' => fake()->randomElement(ExerciseDifficulty::cases())->value,
            'duration_weeks' => fake()->randomElement([4, 6, 8, 12]),
            'sessions_per_week' => fake()->numberBetween(2, 5),
            'is_template' => false,
            'is_active' => true,
            'metadata' => null,
        ];
    }

    public function template(): static
    {
        return $this->state(['is_template' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function forCoach(Coach $coach): static
    {
        return $this->state(['coach_id' => $coach->id]);
    }
}
