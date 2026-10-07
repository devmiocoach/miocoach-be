<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import CoachLayout from '@/layouts/CoachLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import IconPlus from '~icons/heroicons/plus';

import BuilderToolbar from '../../../components/BuilderToolbar.vue';
import DayTabs from '../../../components/DayTabs.vue';
import ExerciseFormModal from '../../../components/ExerciseFormModal.vue';
import ExerciseLibrary from '../../../components/ExerciseLibrary.vue';
import PlanExerciseCard from '../../../components/PlanExerciseCard.vue';
import PlanSettingsPanel from '../../../components/PlanSettingsPanel.vue';
import {
    toBuilderPlan,
    usePlanBuilder,
} from '../../../composables/usePlanBuilder';
import { useSortableList } from '../../../composables/useSortableList';
import type { ExerciseListItem } from '../../../types/exercises';

const props = defineProps<{
    plan: Parameters<typeof toBuilderPlan>[0];
    exercises: ExerciseListItem[];
    clients: Array<{ id: number; name: string }>;
    assignedClientIds?: number[];
}>();

const builder = usePlanBuilder(
    toBuilderPlan(props.plan),
    props.plan?.id ?? null,
);

builder.clientIds.value = props.assignedClientIds ?? [];

const dayListEl = ref<HTMLElement | null>(null);

useSortableList(dayListEl, {
    group: 'exercises',
    handle: '[data-testid="drag-handle"]',
    onReorder: (from, to) =>
        builder.moveExercise(builder.activeDayIndex.value, from, to),
    onAdd: (index, node) => {
        // The dropped node is a clone of an ExerciseCard, which carries its id.
        const exerciseId = Number(node.dataset.exerciseId);
        const source = props.exercises.find(
            (exercise) => exercise.id === exerciseId,
        );

        if (source) {
            builder.addExercise(source, index);
        }
    },
});

const editingExercise = ref<ExerciseListItem | null>(null);
const exerciseModalOpen = ref(false);
const libraryError = ref<string | null>(null);

function createExercise(): void {
    editingExercise.value = null;
    exerciseModalOpen.value = true;
}

function editExercise(exercise: ExerciseListItem): void {
    editingExercise.value = exercise;
    exerciseModalOpen.value = true;
}

// Partial reloads keep the page component mounted, so the in-progress plan
// state in the builder composable survives library refreshes and deletes.
function refreshLibrary(): void {
    router.reload({ only: ['exercises'] });
}

function deleteExercise(exercise: ExerciseListItem): void {
    libraryError.value = null;

    router.delete(route('coach.exercises.destroy', exercise.id), {
        preserveScroll: true,
        preserveState: true,
        only: ['exercises'],
        onError: (errors) => {
            libraryError.value = errors.exercise ?? null;
        },
    });
}

function duplicate(): void {
    if (builder.planId.value !== null) {
        router.post(route('coach.plans.duplicate', builder.planId.value));
    }
}
</script>

<template>
    <CoachLayout
        :title="plan?.name ?? $t('New plan')"
        :breadcrumbs="[
            { title: $t('Workout Plans'), url: route('coach.plans.index') },
            { title: plan?.name ?? $t('New plan') },
        ]"
    >
        <BuilderToolbar
            :is-dirty="builder.isDirty.value"
            :save-state="builder.saveState.value"
            :last-saved-at="builder.lastSavedAt.value"
            :can-duplicate="builder.planId.value !== null"
            @duplicate="duplicate"
        />

        <div
            v-if="builder.isGone.value"
            class="border-b border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200"
            data-testid="plan-gone-banner"
        >
            {{ $t('This plan no longer exists.') }}
            <button
                type="button"
                class="ml-2 font-medium underline"
                data-testid="save-as-new-btn"
                @click="builder.saveAsNew()"
            >
                {{ $t('Save as new') }}
            </button>
        </div>

        <div class="flex flex-1 gap-4 p-4">
            <aside
                class="hidden w-[280px] shrink-0 lg:block"
                data-testid="library-column"
            >
                <div class="flex h-full flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <h2
                            class="text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{ $t('Exercises') }}
                        </h2>
                        <Button
                            size="sm"
                            variant="outline"
                            data-testid="builder-new-exercise-btn"
                            @click="createExercise"
                        >
                            <IconPlus class="mr-1 h-4 w-4" />
                            {{ $t('New') }}
                        </Button>
                    </div>

                    <p
                        v-if="libraryError"
                        class="rounded-md bg-red-50 px-3 py-2 text-xs text-red-800 dark:bg-red-950 dark:text-red-200"
                        data-testid="library-error"
                    >
                        {{ libraryError }}
                    </p>

                    <ExerciseLibrary
                        :exercises="exercises"
                        variant="sidebar"
                        @add="builder.addExercise($event)"
                        @edit="editExercise"
                        @remove="deleteExercise"
                    />
                </div>
            </aside>

            <main class="min-w-0 flex-1">
                <DayTabs
                    :days="builder.plan.days"
                    :active-index="builder.activeDayIndex.value"
                    @select="builder.activeDayIndex.value = $event"
                    @add="builder.addDay()"
                    @remove="builder.removeDay($event)"
                />

                <div
                    v-if="builder.activeDay.value"
                    ref="dayListEl"
                    class="mt-4 flex flex-col gap-3"
                    data-testid="day-exercise-list"
                >
                    <PlanExerciseCard
                        v-for="(exercise, index) in builder.activeDay.value
                            .exercises"
                        :key="exercise.id ?? `new-${index}`"
                        v-model="builder.activeDay.value.exercises[index]"
                        :index="index"
                        :is-first="index === 0"
                        :is-last="
                            index ===
                            builder.activeDay.value.exercises.length - 1
                        "
                        @remove="
                            builder.removeExercise(
                                builder.activeDayIndex.value,
                                index,
                            )
                        "
                        @move-up="
                            builder.moveExercise(
                                builder.activeDayIndex.value,
                                index,
                                index - 1,
                            )
                        "
                        @move-down="
                            builder.moveExercise(
                                builder.activeDayIndex.value,
                                index,
                                index + 1,
                            )
                        "
                        @commit="builder.saveNow()"
                    />

                    <p
                        v-if="builder.activeDay.value.exercises.length === 0"
                        class="rounded-lg border border-dashed border-gray-200 py-16 text-center text-gray-600 dark:border-gray-800 dark:text-gray-400"
                        data-testid="day-empty"
                    >
                        {{ $t('Drag exercises here, or use the + button.') }}
                    </p>
                </div>

                <p
                    v-else
                    class="mt-4 rounded-lg border border-dashed border-gray-200 py-16 text-center text-gray-600 dark:border-gray-800 dark:text-gray-400"
                    data-testid="no-days"
                >
                    {{ $t('Add a day to get started.') }}
                </p>
            </main>

            <aside
                class="hidden w-[240px] shrink-0 xl:block"
                data-testid="settings-column"
            >
                <PlanSettingsPanel
                    :plan="builder.plan"
                    :clients="clients"
                    :selected-client-ids="builder.clientIds.value"
                    @update:selected-client-ids="
                        builder.clientIds.value = $event
                    "
                    @save="builder.saveNow()"
                    @save-and-assign="builder.saveAndAssign()"
                    @commit="builder.saveNow()"
                />
            </aside>
        </div>

        <ExerciseFormModal
            :open="exerciseModalOpen"
            :exercise="editingExercise"
            @close="exerciseModalOpen = false"
            @saved="refreshLibrary"
        />
    </CoachLayout>
</template>
