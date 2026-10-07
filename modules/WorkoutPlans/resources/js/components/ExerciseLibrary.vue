<script setup lang="ts">
import Input from '@/components/ui/input/Input.vue';
import { computed, ref } from 'vue';
import IconSearch from '~icons/heroicons/magnifying-glass';
import { useSortableList } from '../composables/useSortableList';
import type { ExerciseListItem } from '../types/exercises';
import ExerciseCard from './ExerciseCard.vue';
import VideoPreviewModal from './VideoPreviewModal.vue';

const props = defineProps<{
    exercises: ExerciseListItem[];
    variant: 'sidebar' | 'grid';
}>();

const emit = defineEmits<{
    add: [exercise: ExerciseListItem];
    edit: [exercise: ExerciseListItem];
    remove: [exercise: ExerciseListItem];
}>();

const search = ref('');
const typeFilter = ref('');
const muscleFilter = ref('');
const previewing = ref<ExerciseListItem | null>(null);

const types = computed(() =>
    [...new Set(props.exercises.map((e) => e.category).filter(Boolean))].sort(),
);

const muscles = computed(() =>
    [...new Set(props.exercises.flatMap((e) => e.primaryMuscles))].sort(),
);

const filtered = computed(() => {
    const query = search.value.trim().toLowerCase();

    return props.exercises.filter((exercise) => {
        if (query && !exercise.name.toLowerCase().includes(query)) {
            return false;
        }
        if (typeFilter.value && exercise.category !== typeFilter.value) {
            return false;
        }
        if (
            muscleFilter.value &&
            !exercise.primaryMuscles.includes(muscleFilter.value)
        ) {
            return false;
        }
        return true;
    });
});

// The library is a drag SOURCE only: cards clone out, nothing drops in.
// Binding it here rather than in the parent keeps the filtered list and the
// sortable instance in the same component.
const listEl = ref<HTMLElement | null>(null);

if (props.variant === 'sidebar') {
    useSortableList(listEl, { group: 'exercises', clone: true });
}
</script>

<template>
    <div class="flex h-full flex-col gap-3">
        <div class="relative">
            <IconSearch
                class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500"
            />
            <Input
                v-model="search"
                :placeholder="$t('Search exercises')"
                class="pl-9"
                data-testid="library-search"
            />
        </div>

        <div class="flex gap-2">
            <select
                v-model="typeFilter"
                class="flex-1 rounded-md border border-gray-200 bg-white px-2 py-1.5 text-sm text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                data-testid="library-filter-type"
            >
                <option value="">{{ $t('All types') }}</option>
                <option v-for="type in types" :key="type!" :value="type">
                    {{ type }}
                </option>
            </select>
            <select
                v-model="muscleFilter"
                class="flex-1 rounded-md border border-gray-200 bg-white px-2 py-1.5 text-sm text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                data-testid="library-filter-muscle"
            >
                <option value="">{{ $t('All muscles') }}</option>
                <option v-for="muscle in muscles" :key="muscle" :value="muscle">
                    {{ muscle }}
                </option>
            </select>
        </div>

        <div
            ref="listEl"
            class="min-h-0 flex-1 overflow-y-auto"
            :class="
                variant === 'grid'
                    ? 'grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3'
                    : 'flex flex-col gap-2'
            "
            data-testid="library-list"
        >
            <ExerciseCard
                v-for="exercise in filtered"
                :key="exercise.id"
                :exercise="exercise"
                :variant="variant"
                @add="emit('add', $event)"
                @edit="emit('edit', $event)"
                @remove="emit('remove', $event)"
                @preview="previewing = $event"
            />

            <p
                v-if="filtered.length === 0"
                class="col-span-full py-12 text-center text-gray-600 dark:text-gray-400"
                data-testid="library-empty"
            >
                {{ $t('No exercises match your filters.') }}
            </p>
        </div>

        <VideoPreviewModal
            :url="previewing?.videoUrl ?? null"
            :open="previewing !== null"
            @close="previewing = null"
        />
    </div>
</template>
