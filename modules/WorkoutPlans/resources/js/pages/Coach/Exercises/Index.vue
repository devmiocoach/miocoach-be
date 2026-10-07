<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import CoachLayout from '@/layouts/CoachLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import IconPlus from '~icons/heroicons/plus';

import ExerciseFormModal from '../../../components/ExerciseFormModal.vue';
import ExerciseLibrary from '../../../components/ExerciseLibrary.vue';
import type { ExerciseListItem } from '../../../types/exercises';

defineProps<{ exercises: ExerciseListItem[] }>();

const editing = ref<ExerciseListItem | null>(null);
const modalOpen = ref(false);
const deleteError = ref<string | null>(null);

function create(): void {
    editing.value = null;
    modalOpen.value = true;
}

function edit(exercise: ExerciseListItem): void {
    editing.value = exercise;
    modalOpen.value = true;
}

function remove(exercise: ExerciseListItem): void {
    deleteError.value = null;

    router.delete(route('coach.exercises.destroy', exercise.id), {
        preserveScroll: true,
        onError: (errors) => {
            deleteError.value = errors.exercise ?? null;
        },
    });
}

const title = 'Exercises';
</script>

<template>
    <CoachLayout
        :title="title"
        :breadcrumbs="[
            { title: $t('Workout Plans'), url: route('coach.plans.index') },
            { title: $t('Exercises') },
        ]"
    >
        <div class="flex flex-1 flex-col gap-6 p-6 pt-2">
            <div class="flex items-start justify-between">
                <h1
                    class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white"
                >
                    {{ $t('Exercises') }}
                </h1>
                <Button data-testid="new-exercise-btn" @click="create">
                    <IconPlus class="mr-2 h-4 w-4" />
                    {{ $t('New exercise') }}
                </Button>
            </div>

            <p
                v-if="deleteError"
                class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 dark:bg-red-950 dark:text-red-200"
                data-testid="delete-error"
            >
                {{ deleteError }}
            </p>

            <ExerciseLibrary
                :exercises="exercises"
                variant="grid"
                @add="() => {}"
                @edit="edit"
                @remove="remove"
            />
        </div>

        <ExerciseFormModal
            :open="modalOpen"
            :exercise="editing"
            @close="modalOpen = false"
            @saved="router.reload({ only: ['exercises'] })"
        />
    </CoachLayout>
</template>
