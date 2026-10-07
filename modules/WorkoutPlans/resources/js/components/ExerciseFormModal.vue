<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import Dialog from '@/components/ui/dialog/Dialog.vue';
import DialogContent from '@/components/ui/dialog/DialogContent.vue';
import Input from '@/components/ui/input/Input.vue';
import { reactive, ref, watch } from 'vue';
import type { ExerciseListItem } from '../types/exercises';

const props = defineProps<{
    open: boolean;
    exercise: ExerciseListItem | null;
}>();

const emit = defineEmits<{ close: []; saved: [] }>();

type FormState = {
    name: string;
    type: string;
    primaryMuscles: string[];
    videoUrl: string;
    instructions: string;
};

const form = reactive<FormState>({
    name: '',
    type: '',
    primaryMuscles: [],
    videoUrl: '',
    instructions: '',
});

const errors = ref<{ name?: string; type?: string; videoUrl?: string }>({});
const processing = ref(false);
const muscleDraft = ref('');

const CATEGORIES: Modules.WorkoutPlans.Enums.ExerciseCategory[] = [
    'strength',
    'cardio',
    'flexibility',
    'mobility',
    'balance',
    'hiit',
    'stretching',
    'warmup',
    'cooldown',
    'sport',
];

/** Accepts "petto, tricipiti" in one go so pasting a list works. */
function commitMuscleDraft(): void {
    const added = muscleDraft.value
        .split(',')
        .map((muscle) => muscle.trim())
        .filter((muscle) => muscle && !form.primaryMuscles.includes(muscle));

    form.primaryMuscles.push(...added);
    muscleDraft.value = '';
}

function removeMuscle(muscle: string): void {
    form.primaryMuscles = form.primaryMuscles.filter((m) => m !== muscle);
}

// Re-seed the form whenever the modal target changes (create = null, edit = row).
watch(
    () => props.exercise,
    (exercise) => {
        form.name = exercise?.name ?? '';
        // exercises.category is NOT NULL, so a new exercise starts on a real type.
        form.type = exercise?.category ?? 'strength';
        form.primaryMuscles = [...(exercise?.primaryMuscles ?? [])];
        form.videoUrl = exercise?.videoUrl ?? '';
        form.instructions = exercise?.instructions ?? '';
        muscleDraft.value = '';
        errors.value = {};
    },
    { immediate: true },
);

function xsrf(): string {
    return decodeURIComponent(
        document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
    );
}

async function submit(): Promise<void> {
    if (processing.value) {
        return;
    }

    processing.value = true;
    errors.value = {};

    // A muscle still sitting in the input would otherwise be lost on submit.
    commitMuscleDraft();

    const editing = props.exercise !== null;
    const url = editing
        ? route('coach.exercises.update', props.exercise!.id)
        : route('coach.exercises.store');

    try {
        const response = await fetch(url, {
            method: editing ? 'PUT' : 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrf(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                name: form.name,
                type: form.type,
                primaryMuscles: form.primaryMuscles,
                videoUrl: form.videoUrl,
                instructions: form.instructions,
            }),
        });

        if (response.status === 422) {
            const body = await response.json();
            const fields = body?.error?.fields ?? {};
            errors.value = {
                name: fields.name?.[0],
                type: fields.type?.[0],
                videoUrl: fields.videoUrl?.[0],
            };
            return;
        }

        if (!response.ok) {
            throw new Error(`save failed: ${response.status}`);
        }

        emit('saved');
        emit('close');
    } catch {
        // Network/5xx: surface a generic message on the video field slot.
        errors.value = { videoUrl: 'Something went wrong. Please retry.' };
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('close')">
        <DialogContent
            class="bg-white dark:bg-gray-900"
            data-testid="exercise-form-modal"
        >
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <label class="text-sm text-gray-600 dark:text-gray-400">
                    {{ $t('Name') }}
                    <Input
                        v-model="form.name"
                        data-testid="exercise-form-name"
                    />
                    <p
                        v-if="errors.name"
                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                    >
                        {{ errors.name }}
                    </p>
                </label>

                <label class="text-sm text-gray-600 dark:text-gray-400">
                    {{ $t('Type') }}
                    <select
                        v-model="form.type"
                        required
                        class="mt-1 w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                        data-testid="exercise-form-type"
                    >
                        <option
                            v-for="category in CATEGORIES"
                            :key="category"
                            :value="category"
                        >
                            {{ $t(category) }}
                        </option>
                    </select>
                    <p
                        v-if="errors.type"
                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                        data-testid="exercise-form-type-error"
                    >
                        {{ errors.type }}
                    </p>
                </label>

                <div class="text-sm text-gray-600 dark:text-gray-400">
                    {{ $t('Primary muscles') }}
                    <div
                        class="mt-1 flex flex-wrap gap-1"
                        data-testid="exercise-form-muscle-list"
                    >
                        <span
                            v-for="muscle in form.primaryMuscles"
                            :key="muscle"
                            class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-900 dark:bg-gray-800 dark:text-white"
                        >
                            {{ muscle }}
                            <button
                                type="button"
                                class="text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400"
                                :data-testid="`remove-muscle-${muscle}`"
                                :aria-label="$t('Remove')"
                                @click="removeMuscle(muscle)"
                            >
                                ×
                            </button>
                        </span>
                    </div>
                    <Input
                        v-model="muscleDraft"
                        :placeholder="$t('Add a muscle and press Enter')"
                        class="mt-1"
                        data-testid="exercise-form-muscle-input"
                        @keydown.enter.prevent="commitMuscleDraft"
                        @blur="commitMuscleDraft"
                    />
                </div>

                <label class="text-sm text-gray-600 dark:text-gray-400">
                    {{ $t('Video URL') }}
                    <Input
                        v-model="form.videoUrl"
                        placeholder="https://youtu.be/…"
                        data-testid="exercise-form-video"
                    />
                    <p
                        v-if="errors.videoUrl"
                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                        data-testid="exercise-form-video-error"
                    >
                        {{ errors.videoUrl }}
                    </p>
                </label>

                <label class="text-sm text-gray-600 dark:text-gray-400">
                    {{ $t('Instructions') }}
                    <textarea
                        v-model="form.instructions"
                        rows="4"
                        class="mt-1 w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                        data-testid="exercise-form-instructions"
                    />
                </label>

                <div class="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('close')"
                    >
                        {{ $t('Cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-testid="exercise-form-submit"
                    >
                        {{ $t('Save') }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
