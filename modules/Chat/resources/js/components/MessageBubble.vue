<script setup lang="ts">
import { computed } from 'vue';

export type MessageAttachment = {
    id: number;
    original_name: string;
    mime: string;
    size_bytes: number;
    download_url: string;
};

const props = defineProps<{
    type: string;
    body: string | null;
    attachments?: MessageAttachment[];
    meta?: Record<string, unknown> | null;
    mine: boolean;
    createdAt: string;
    read: boolean;
}>();

const attachment = computed(() => props.attachments?.[0] ?? null);

const workoutPlan = computed(() => {
    if (props.type !== 'workout_plan' || !props.meta) {
        return null;
    }
    return {
        id: props.meta.workout_plan_id as number,
        name: (props.meta.name as string) ?? 'Scheda',
    };
});

function time(iso: string): string {
    return new Date(iso).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <div class="flex" :class="mine ? 'justify-end' : 'justify-start'">
        <div
            :data-testid="mine ? 'bubble-mine' : 'bubble-theirs'"
            class="max-w-[70%] rounded-2xl px-4 py-2 text-sm"
            :class="
                mine
                    ? 'bg-primary dark:bg-primary text-white'
                    : 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-white'
            "
        >
            <a
                v-if="workoutPlan"
                :href="route('coach.plans.edit', workoutPlan.id)"
                data-testid="workout-plan-card"
                class="flex items-center gap-3 rounded-lg border p-2"
                :class="
                    mine
                        ? 'border-white/25 hover:bg-white/10'
                        : 'border-gray-300 hover:bg-gray-200 dark:border-gray-600 dark:hover:bg-gray-700'
                "
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    class="h-6 w-6 shrink-0"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"
                    />
                </svg>
                <span class="flex flex-col">
                    <span class="text-xs opacity-70"
                        >Scheda di allenamento</span
                    >
                    <span class="font-medium break-words">{{
                        workoutPlan.name
                    }}</span>
                </span>
            </a>
            <img
                v-else-if="type === 'image' && attachment"
                :src="attachment.download_url"
                :alt="attachment.original_name"
                data-testid="attachment-image"
                class="max-h-64 max-w-full rounded-lg border border-black/10 dark:border-white/10"
            />
            <a
                v-else-if="type === 'file' && attachment"
                :href="attachment.download_url"
                data-testid="attachment-file-link"
                class="flex items-center gap-2 underline underline-offset-2 hover:opacity-80"
                download
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    class="h-4 w-4 shrink-0"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3 3m0 0l-3-3m3 3V9"
                    />
                </svg>
                <span class="break-all">{{ attachment.original_name }}</span>
            </a>
            <p v-else class="break-words whitespace-pre-wrap">{{ body }}</p>
            <div
                class="mt-1 flex items-center justify-end gap-1 text-xs opacity-70"
            >
                <span>{{ time(createdAt) }}</span>
                <span v-if="mine" data-testid="read-status">{{
                    read ? '✓✓' : '✓'
                }}</span>
            </div>
        </div>
    </div>
</template>
