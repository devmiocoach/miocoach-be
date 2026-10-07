import { computed, onScopeDispose, reactive, ref, watch } from 'vue';
import type { ExerciseListItem } from '../types/exercises';

export type BuilderExercise = {
    id: number | null;
    exerciseId: number;
    name: string;
    sets: number | null;
    repsTarget: number | null;
    weightKg: number | null;
    rirTarget: number | null;
    restSeconds: number | null;
    notes: string | null;
};

export type BuilderDay = {
    id: number | null;
    dayNumber: number;
    name: string | null;
    exercises: BuilderExercise[];
};

export type BuilderPlan = {
    name: string;
    description: string | null;
    goal: string | null;
    difficulty: string | null;
    durationWeeks: number | null;
    sessionsPerWeek: number | null;
    isTemplate: boolean;
    days: BuilderDay[];
};

type SaveState = 'idle' | 'saving' | 'error';

const AUTOSAVE_DELAY_MS = 30_000;
const RETRY_DELAYS_MS = [2_000, 8_000, 30_000];

/** The shape PlanBuilderController@edit sends: a WorkoutPlanDetailResource. */
type PlanResource = {
    id: number;
    name: string;
    description: string | null;
    goal: string | null;
    difficulty: string | null;
    durationWeeks: number | null;
    sessionsPerWeek: number | null;
    isTemplate: boolean;
    days: Array<{
        id: number;
        dayNumber: number;
        name: string | null;
        exercises: Array<{
            id: number;
            exerciseId: number;
            exercise: { name: string };
            sets: number | null;
            repsTarget: number | null;
            weightKg: number | null;
            rirTarget: number | null;
            restSeconds: number | null;
            notes: string | null;
        }>;
    }>;
};

export const EMPTY_PLAN: BuilderPlan = {
    name: '',
    description: null,
    goal: null,
    difficulty: null,
    durationWeeks: null,
    sessionsPerWeek: null,
    isTemplate: false,
    days: [],
};

/** Flatten the server resource into the flat shape the builder edits. */
export function toBuilderPlan(resource: PlanResource | null): BuilderPlan {
    if (resource === null) {
        return structuredClone(EMPTY_PLAN);
    }

    return {
        name: resource.name,
        description: resource.description,
        goal: resource.goal,
        difficulty: resource.difficulty,
        durationWeeks: resource.durationWeeks,
        sessionsPerWeek: resource.sessionsPerWeek,
        isTemplate: resource.isTemplate,
        days: resource.days.map((day) => ({
            id: day.id,
            dayNumber: day.dayNumber,
            name: day.name,
            exercises: day.exercises.map((exercise) => ({
                id: exercise.id,
                exerciseId: exercise.exerciseId,
                name: exercise.exercise.name,
                sets: exercise.sets,
                repsTarget: exercise.repsTarget,
                weightKg: exercise.weightKg,
                rirTarget: exercise.rirTarget,
                restSeconds: exercise.restSeconds,
                notes: exercise.notes,
            })),
        })),
    };
}

export function usePlanBuilder(
    initialPlan: BuilderPlan,
    initialId: number | null,
) {
    const plan = reactive<BuilderPlan>(structuredClone(initialPlan));
    const planId = ref<number | null>(initialId);
    const clientIds = ref<number[]>([]);
    const isDirty = ref(false);
    const lastSavedAt = ref<Date | null>(null);
    const saveState = ref<SaveState>('idle');
    const isGone = ref(false);
    const activeDayIndex = ref(0);

    let debounceTimer: ReturnType<typeof setTimeout> | null = null;
    let retryTimer: ReturnType<typeof setTimeout> | null = null;
    let inFlight = false;
    let savePending = false;

    const activeDay = computed<BuilderDay | undefined>(
        () => plan.days[activeDayIndex.value],
    );

    /** A plan is worth persisting once it has a name or any exercise. */
    const hasSomethingToSave = computed(
        () =>
            plan.name.trim() !== '' ||
            plan.days.some((day) => day.exercises.length > 0),
    );

    function payload(includeClientIds: boolean): Record<string, unknown> {
        const body: Record<string, unknown> = {
            name: plan.name,
            description: plan.description,
            goal: plan.goal,
            difficulty: plan.difficulty,
            durationWeeks: plan.durationWeeks,
            sessionsPerWeek: plan.sessionsPerWeek,
            isTemplate: plan.isTemplate,
            days: plan.days.map((day) => ({
                id: day.id,
                dayNumber: day.dayNumber,
                name: day.name,
                exercises: day.exercises.map((exercise) => ({
                    id: exercise.id,
                    exerciseId: exercise.exerciseId,
                    sets: exercise.sets,
                    repsTarget: exercise.repsTarget,
                    weightKg: exercise.weightKg,
                    rirTarget: exercise.rirTarget,
                    restSeconds: exercise.restSeconds,
                    notes: exercise.notes,
                })),
            })),
        };

        // Auto-save must never touch assignments.
        if (includeClientIds) {
            body.clientIds = clientIds.value;
        }

        return body;
    }

    /** Copy server-assigned ids back into local state so the next save upserts. */
    function absorbIds(saved: {
        id: number;
        days?: Array<{ id: number; exercises?: Array<{ id: number }> }>;
    }): void {
        planId.value = saved.id;

        saved.days?.forEach((savedDay, dayIndex) => {
            const day = plan.days[dayIndex];

            if (!day) {
                return;
            }

            day.id = savedDay.id;

            savedDay.exercises?.forEach((savedExercise, exerciseIndex) => {
                const exercise = day.exercises[exerciseIndex];

                if (exercise) {
                    exercise.id = savedExercise.id;
                }
            });
        });
    }

    async function save({
        withAssignments = false,
        attempt = 0,
    } = {}): Promise<void> {
        if (isGone.value || !hasSomethingToSave.value) {
            return;
        }

        if (inFlight) {
            savePending = true;
            return;
        }

        inFlight = true;
        saveState.value = 'saving';

        const creating = planId.value === null;
        const url = creating
            ? route('coach.plans.store')
            : route('coach.plans.update', planId.value!);

        try {
            const response = await fetch(url, {
                method: creating ? 'POST' : 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': decodeURIComponent(
                        document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                    ),
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload(withAssignments)),
            });

            if (response.status === 404) {
                isGone.value = true;
                saveState.value = 'error';
                return;
            }

            if (!response.ok) {
                throw new Error(`save failed: ${response.status}`);
            }

            const body = await response.json();
            absorbIds(body.plan);

            // First save promoted a draft to a persisted plan: rewrite the URL
            // from /create to /{id}/edit so a manual reload reloads the saved
            // plan instead of a blank create form.
            if (
                creating &&
                planId.value !== null &&
                typeof window !== 'undefined'
            ) {
                window.history.replaceState(
                    window.history.state,
                    '',
                    route('coach.plans.edit', planId.value),
                );
            }

            // A pending retry from an earlier failure is now moot.
            if (retryTimer) {
                clearTimeout(retryTimer);
                retryTimer = null;
            }

            saveState.value = 'idle';
            isDirty.value = false;
            lastSavedAt.value = new Date();
        } catch (error) {
            saveState.value = 'error';

            if (attempt < RETRY_DELAYS_MS.length) {
                retryTimer = setTimeout(
                    () => void save({ withAssignments, attempt: attempt + 1 }),
                    RETRY_DELAYS_MS[attempt],
                );
            }
        } finally {
            inFlight = false;

            if (savePending) {
                savePending = false;
                void save({ withAssignments });
            }
        }
    }

    /** Called on blur of an inline field: persist immediately, don't wait 30s. */
    function saveNow(): void {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
            debounceTimer = null;
        }

        void save();
    }

    function saveAndAssign(): Promise<void> {
        return save({ withAssignments: true });
    }

    /** The plan was deleted elsewhere; re-create it under a new id. */
    function saveAsNew(): Promise<void> {
        isGone.value = false;
        planId.value = null;

        plan.days.forEach((day) => {
            day.id = null;
            day.exercises.forEach((exercise) => {
                exercise.id = null;
            });
        });

        return save();
    }

    function addDay(): void {
        const dayNumber = plan.days.length + 1;
        plan.days.push({ id: null, dayNumber, name: null, exercises: [] });
        activeDayIndex.value = plan.days.length - 1;
    }

    function removeDay(index: number): void {
        plan.days.splice(index, 1);
        plan.days.forEach((day, position) => {
            day.dayNumber = position + 1;
        });
        activeDayIndex.value = Math.max(
            0,
            Math.min(activeDayIndex.value, plan.days.length - 1),
        );
    }

    function addExercise(source: ExerciseListItem, atIndex?: number): void {
        if (plan.days.length === 0) {
            addDay();
        }

        const day = plan.days[activeDayIndex.value];

        const item: BuilderExercise = {
            id: null,
            exerciseId: source.id,
            name: source.name,
            sets: 3,
            repsTarget: 10,
            weightKg: null,
            rirTarget: 2,
            restSeconds: 90,
            notes: null,
        };

        if (atIndex === undefined) {
            day.exercises.push(item);
        } else {
            day.exercises.splice(atIndex, 0, item);
        }
    }

    function removeExercise(dayIndex: number, exerciseIndex: number): void {
        plan.days[dayIndex].exercises.splice(exerciseIndex, 1);
    }

    function moveExercise(dayIndex: number, from: number, to: number): void {
        const exercises = plan.days[dayIndex].exercises;

        if (to < 0 || to >= exercises.length) {
            return;
        }

        const [item] = exercises.splice(from, 1);
        exercises.splice(to, 0, item);
    }

    watch(
        plan,
        () => {
            isDirty.value = true;

            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }

            debounceTimer = setTimeout(() => void save(), AUTOSAVE_DELAY_MS);
        },
        { deep: true },
    );

    // A closed tab must not eat twenty minutes of work.
    // `keepalive` lets the request outlive the document; sendBeacon cannot do
    // this because it only issues POST, and this route is a PUT.
    function flushOnUnload(): void {
        if (
            !isDirty.value ||
            !hasSomethingToSave.value ||
            planId.value === null
        ) {
            return;
        }

        void fetch(route('coach.plans.update', planId.value), {
            method: 'PUT',
            keepalive: true,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                ),
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload(false)),
        });
    }

    window.addEventListener('beforeunload', flushOnUnload);

    // Inertia keeps the SPA alive across visits, so the listener and any pending
    // timers must be torn down when the builder unmounts — otherwise each visit
    // leaks a stale closure that fires a PUT on tab close.
    onScopeDispose(() => {
        window.removeEventListener('beforeunload', flushOnUnload);

        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }

        if (retryTimer) {
            clearTimeout(retryTimer);
        }
    });

    return {
        plan,
        planId,
        clientIds,
        isDirty,
        lastSavedAt,
        saveState,
        isGone,
        activeDayIndex,
        activeDay,
        save,
        saveNow,
        saveAndAssign,
        saveAsNew,
        addDay,
        removeDay,
        addExercise,
        removeExercise,
        moveExercise,
    };
}
