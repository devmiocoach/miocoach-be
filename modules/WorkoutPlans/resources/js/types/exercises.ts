export type ExerciseListItem = {
    id: number;
    name: string;
    description: string | null;
    instructions: string | null;
    category: Modules.WorkoutPlans.Enums.ExerciseCategory | null;
    difficulty: Modules.WorkoutPlans.Enums.ExerciseDifficulty | null;
    primaryMuscles: string[];
    secondaryMuscles: string[];
    muscleGroups: string[];
    equipment: string[];
    videoUrl: string | null;
    thumbnailUrl: string | null;
    isCustom: boolean;
    coachId: number | null;
    isActive: boolean;
    createdAt: string | null;
};
