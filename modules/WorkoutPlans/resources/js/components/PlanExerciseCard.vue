<script setup lang="ts">
import Input from '@/components/ui/input/Input.vue';
import { computed } from 'vue';
import IconArrowDown from '~icons/heroicons/arrow-down';
import IconArrowUp from '~icons/heroicons/arrow-up';
import IconBars from '~icons/heroicons/bars-3';
import IconXMark from '~icons/heroicons/x-mark';
import type { BuilderExercise } from '../composables/usePlanBuilder';

/**
 * Two-way bound so the inline fields never mutate a prop. The parent owns the
 * array; this card owns one entry while it is on screen.
 */
const exercise = defineModel<BuilderExercise>({ required: true });

defineProps<{
    index: number;
    isFirst: boolean;
    isLast: boolean;
}>();

defineEmits<{
    remove: [];
    moveUp: [];
    moveDown: [];
    commit: [];
}>();

/**
 * `Input`'s `modelValue` is typed `string | number` (no `null`), while these
 * fields are nullable. A small getter/setter proxy per field keeps that
 * conversion local to this component instead of loosening the shared `Input`
 * component's contract or the composable's nullable field types.
 */
function numberField(
    key: 'sets' | 'repsTarget' | 'weightKg' | 'rirTarget' | 'restSeconds',
) {
    return computed<string | number>({
        get: () => exercise.value[key] ?? '',
        set: (value) => {
            const parsed =
                typeof value === 'number' ? value : parseFloat(value);
            exercise.value[key] = Number.isNaN(parsed) ? null : parsed;
        },
    });
}

const setsModel = numberField('sets');
const repsModel = numberField('repsTarget');
const weightModel = numberField('weightKg');
const rirModel = numberField('rirTarget');
const restModel = numberField('restSeconds');

const notesModel = computed<string>({
    get: () => exercise.value.notes ?? '',
    set: (value) => {
        exercise.value.notes = value === '' ? null : value;
    },
});
</script>

<template>
    <div
        class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900"
        :data-testid="`plan-exercise-${index}`"
    >
        <div class="flex items-center gap-2">
            <IconBars
                class="h-4 w-4 cursor-grab text-gray-400 dark:text-gray-500"
                data-testid="drag-handle"
            />
            <p
                class="flex-1 truncate font-medium text-gray-900 dark:text-white"
            >
                {{ exercise.name }}
            </p>
            <button
                type="button"
                :disabled="isFirst"
                class="text-gray-400 disabled:opacity-30 dark:text-gray-500"
                :data-testid="`move-up-${index}`"
                :aria-label="$t('Move up')"
                @click="$emit('moveUp')"
            >
                <IconArrowUp class="h-4 w-4" />
            </button>
            <button
                type="button"
                :disabled="isLast"
                class="text-gray-400 disabled:opacity-30 dark:text-gray-500"
                :data-testid="`move-down-${index}`"
                :aria-label="$t('Move down')"
                @click="$emit('moveDown')"
            >
                <IconArrowDown class="h-4 w-4" />
            </button>
            <button
                type="button"
                class="text-gray-400 hover:text-red-600 dark:text-gray-500 dark:hover:text-red-400"
                :data-testid="`remove-exercise-${index}`"
                :aria-label="$t('Remove exercise')"
                @click="$emit('remove')"
            >
                <IconXMark class="h-4 w-4" />
            </button>
        </div>

        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-5">
            <label class="text-xs text-gray-600 dark:text-gray-400">
                {{ $t('Sets') }}
                <Input
                    v-model="setsModel"
                    type="number"
                    min="1"
                    max="20"
                    :data-testid="`field-sets-${index}`"
                    @blur="$emit('commit')"
                />
            </label>
            <label class="text-xs text-gray-600 dark:text-gray-400">
                {{ $t('Reps') }}
                <Input
                    v-model="repsModel"
                    type="number"
                    min="1"
                    max="100"
                    :data-testid="`field-reps-${index}`"
                    @blur="$emit('commit')"
                />
            </label>
            <label class="text-xs text-gray-600 dark:text-gray-400">
                {{ $t('Kg') }}
                <Input
                    v-model="weightModel"
                    type="number"
                    step="0.5"
                    min="0"
                    max="999.99"
                    :data-testid="`field-weight-${index}`"
                    @blur="$emit('commit')"
                />
            </label>
            <label class="text-xs text-gray-600 dark:text-gray-400">
                {{ $t('RIR') }}
                <Input
                    v-model="rirModel"
                    type="number"
                    min="0"
                    max="10"
                    :data-testid="`field-rir-${index}`"
                    @blur="$emit('commit')"
                />
            </label>
            <label class="text-xs text-gray-600 dark:text-gray-400">
                {{ $t('Rest (s)') }}
                <Input
                    v-model="restModel"
                    type="number"
                    min="0"
                    max="3600"
                    :data-testid="`field-rest-${index}`"
                    @blur="$emit('commit')"
                />
            </label>
        </div>

        <Input
            v-model="notesModel"
            :placeholder="$t('Notes')"
            class="mt-2"
            :data-testid="`field-notes-${index}`"
            @blur="$emit('commit')"
        />
    </div>
</template>
