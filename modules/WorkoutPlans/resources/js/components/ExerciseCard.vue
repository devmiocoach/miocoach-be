<script setup lang="ts">
import Badge from '@/components/ui/badge/Badge.vue';
import Button from '@/components/ui/button/Button.vue';
import { ref } from 'vue';
import IconEllipsis from '~icons/heroicons/ellipsis-vertical';
import IconPlay from '~icons/heroicons/play-circle';
import IconPlus from '~icons/heroicons/plus';
import type { ExerciseListItem } from '../types/exercises';

defineProps<{
    exercise: ExerciseListItem;
    variant: 'sidebar' | 'grid';
}>();

const emit = defineEmits<{
    add: [exercise: ExerciseListItem];
    preview: [exercise: ExerciseListItem];
    edit: [exercise: ExerciseListItem];
    remove: [exercise: ExerciseListItem];
}>();

const menuOpen = ref(false);
</script>

<template>
    <div
        class="group relative flex gap-3 rounded-lg border border-gray-200 bg-white p-3 transition hover:border-gray-300 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-gray-700"
        :class="variant === 'grid' ? 'flex-col' : 'flex-row items-center'"
        :data-testid="`exercise-card-${exercise.id}`"
        :data-exercise-id="exercise.id"
    >
        <button
            type="button"
            class="relative shrink-0 overflow-hidden rounded-md bg-gray-100 dark:bg-gray-800"
            :class="variant === 'grid' ? 'aspect-video w-full' : 'h-12 w-12'"
            :data-testid="`preview-exercise-${exercise.id}`"
            @click="emit('preview', exercise)"
        >
            <img
                v-if="exercise.thumbnailUrl"
                :src="exercise.thumbnailUrl"
                :alt="exercise.name"
                class="h-full w-full object-cover"
            />
            <IconPlay
                v-else
                class="absolute inset-0 m-auto h-6 w-6 text-gray-400 dark:text-gray-500"
            />
        </button>

        <div class="min-w-0 flex-1">
            <p class="truncate font-medium text-gray-900 dark:text-white">
                {{ exercise.name }}
            </p>
            <div class="mt-1 flex flex-wrap gap-1">
                <Badge
                    v-for="muscle in exercise.primaryMuscles"
                    :key="muscle"
                    variant="secondary"
                >
                    {{ muscle }}
                </Badge>
                <Badge v-if="exercise.isCustom" variant="outline">{{
                    $t('Custom')
                }}</Badge>
            </div>
        </div>

        <Button
            v-if="variant === 'sidebar'"
            size="icon"
            variant="ghost"
            :data-testid="`add-exercise-${exercise.id}`"
            :aria-label="$t('Add to day')"
            @click="emit('add', exercise)"
        >
            <IconPlus class="h-4 w-4" />
        </Button>

        <div v-if="exercise.isCustom" class="relative">
            <Button
                size="icon"
                variant="ghost"
                :data-testid="`exercise-actions-${exercise.id}`"
                @click="menuOpen = !menuOpen"
            >
                <IconEllipsis class="h-4 w-4" />
            </Button>
            <div
                v-if="menuOpen"
                class="absolute right-0 z-10 mt-1 w-36 rounded-md border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-800 dark:bg-gray-900"
            >
                <button
                    type="button"
                    class="block w-full px-3 py-1.5 text-left text-sm text-gray-900 hover:bg-gray-50 dark:text-white dark:hover:bg-gray-800"
                    :data-testid="`edit-exercise-${exercise.id}`"
                    @click="
                        menuOpen = false;
                        emit('edit', exercise);
                    "
                >
                    {{ $t('Edit') }}
                </button>
                <button
                    type="button"
                    class="block w-full px-3 py-1.5 text-left text-sm text-red-600 hover:bg-gray-50 dark:text-red-400 dark:hover:bg-gray-800"
                    :data-testid="`delete-exercise-${exercise.id}`"
                    @click="
                        menuOpen = false;
                        emit('remove', exercise);
                    "
                >
                    {{ $t('Delete') }}
                </button>
            </div>
        </div>
    </div>
</template>
