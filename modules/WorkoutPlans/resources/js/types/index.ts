export type ExerciseCategory = Modules.WorkoutPlans.Enums.ExerciseCategory;

export type ExerciseDifficulty = Modules.WorkoutPlans.Enums.ExerciseDifficulty;

export type WorkoutGoal = Modules.WorkoutPlans.Enums.WorkoutGoal;

export interface Exercise {
    id: number;
    name: string;
    description: string | null;
    category: ExerciseCategory;
    difficulty: ExerciseDifficulty;
    muscle_groups: string[] | null;
    equipment: string[] | null;
    is_active: boolean;
}

export interface WorkoutPlan {
    id: number;
    name: string;
    description: string | null;
    goal: WorkoutGoal | null;
    difficulty: ExerciseDifficulty | null;
    duration_weeks: number | null;
    sessions_per_week: number | null;
    is_template: boolean;
    is_active: boolean;
    created_at: string;
}
