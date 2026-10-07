<script setup lang="ts">
import { ref } from 'vue';

type Plan = { id: number; name: string };

const props = defineProps<{
    plans?: Plan[];
}>();

const emit = defineEmits<{
    send: [body: string];
    typing: [];
    attach: [file: File];
    plan: [planId: number];
}>();
const body = ref('');
const fileInput = ref<HTMLInputElement | null>(null);
const showEmoji = ref(false);
const showPlans = ref(false);

const emojis = [
    '😀',
    '😂',
    '😍',
    '👍',
    '🙏',
    '🔥',
    '💪',
    '🎉',
    '❤️',
    '😅',
    '🤔',
    '👏',
    '🙌',
    '✅',
    '⚡',
    '🏋️',
];

function submit() {
    const value = body.value.trim();
    if (!value) {
        return;
    }
    emit('send', value);
    body.value = '';
}

function openFilePicker() {
    fileInput.value?.click();
}

function onFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        emit('attach', file);
    }
    target.value = '';
}

function addEmoji(char: string) {
    body.value += char;
    showEmoji.value = false;
}

function selectPlan(planId: number) {
    emit('plan', planId);
    showPlans.value = false;
}
</script>

<template>
    <form
        class="relative flex items-center gap-2 border-t border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900"
        @submit.prevent="submit"
    >
        <input
            ref="fileInput"
            data-testid="chat-attach-input"
            type="file"
            class="hidden"
            accept="image/jpeg,image/png,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
            @change="onFileChange"
        />

        <!-- Attach file -->
        <button
            type="button"
            data-testid="chat-attach"
            title="Allega un file"
            class="shrink-0 rounded-full p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
            @click="openFilePicker"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                class="h-5 w-5"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"
                />
            </svg>
        </button>

        <!-- Send workout plan -->
        <button
            v-if="plans && plans.length"
            type="button"
            data-testid="plan-toggle"
            title="Invia una scheda"
            class="shrink-0 rounded-full p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
            @click="showPlans = !showPlans"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                class="h-5 w-5"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                />
            </svg>
        </button>

        <!-- Emoji -->
        <button
            type="button"
            data-testid="emoji-toggle"
            title="Emoji"
            class="shrink-0 rounded-full p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
            @click="showEmoji = !showEmoji"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                class="h-5 w-5"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M15.182 15.182a4.5 4.5 0 01-6.364 0M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75zm-.375 0h.008v.015h-.008V9.75zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75zm-.375 0h.008v.015h-.008V9.75z"
                />
            </svg>
        </button>

        <!-- Emoji popover -->
        <div
            v-if="showEmoji"
            data-testid="emoji-picker"
            class="absolute bottom-16 left-3 z-10 grid grid-cols-8 gap-1 rounded-lg border border-gray-200 bg-white p-2 shadow-lg dark:border-gray-700 dark:bg-gray-800"
        >
            <button
                v-for="char in emojis"
                :key="char"
                type="button"
                :data-testid="`emoji-${char}`"
                class="rounded p-1 text-lg hover:bg-gray-100 dark:hover:bg-gray-700"
                @click="addEmoji(char)"
            >
                {{ char }}
            </button>
        </div>

        <!-- Plan picker popover -->
        <div
            v-if="showPlans && plans"
            data-testid="plan-picker"
            class="absolute bottom-16 left-3 z-10 max-h-64 w-72 overflow-y-auto rounded-lg border border-gray-200 bg-white p-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
        >
            <button
                v-for="plan in plans"
                :key="plan.id"
                type="button"
                :data-testid="`send-plan-${plan.id}`"
                class="flex w-full items-center gap-2 rounded px-3 py-2 text-left text-sm text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700"
                @click="selectPlan(plan.id)"
            >
                <span class="truncate">{{ plan.name }}</span>
            </button>
        </div>

        <input
            v-model="body"
            data-testid="chat-input"
            type="text"
            placeholder="Scrivi un messaggio…"
            class="flex-1 rounded-full border border-gray-300 bg-white px-4 py-2 text-sm text-gray-900 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white"
            @input="emit('typing')"
        />
        <button
            type="submit"
            data-testid="chat-send"
            class="bg-primary rounded-full px-4 py-2 text-sm font-medium text-white"
        >
            Invia
        </button>
    </form>
</template>
