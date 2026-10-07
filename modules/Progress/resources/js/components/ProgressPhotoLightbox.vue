<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';

import type { ProgressPhoto } from '../types';

defineProps<{
    photo: ProgressPhoto | null;
    open: boolean;
}>();

const emit = defineEmits<{
    close: [];
}>();

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('it-IT', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-200"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open && photo"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/90"
                data-testid="photo-lightbox"
                @click.self="emit('close')"
                @keydown.escape="emit('close')"
            >
                <div class="relative max-h-[90vh] max-w-[90vw]">
                    <img
                        :src="`/storage/${photo.storage_key}`"
                        :alt="`Foto ${formatDate(photo.taken_at)}`"
                        class="max-h-[85vh] rounded-lg object-contain"
                    />
                    <div
                        class="absolute inset-x-0 bottom-0 rounded-b-lg bg-black/60 p-4"
                    >
                        <p class="text-sm text-white">
                            {{ formatDate(photo.taken_at) }}
                        </p>
                        <p
                            v-if="photo.notes"
                            class="mt-1 text-xs text-gray-300"
                        >
                            {{ photo.notes }}
                        </p>
                    </div>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="absolute top-2 right-2 text-white hover:bg-white/20"
                        data-testid="lightbox-close-btn"
                        @click="emit('close')"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-6 w-6"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </Button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
