<script setup lang="ts">
import type { ProgressPhoto } from '../types';

defineProps<{
    photos: ProgressPhoto[];
    loading: boolean;
}>();

const emit = defineEmits<{
    view: [photo: ProgressPhoto];
}>();

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('it-IT', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}
</script>

<template>
    <div data-testid="progress-photo-grid">
        <div v-if="loading" class="grid grid-cols-3 gap-3">
            <div
                v-for="i in 6"
                :key="i"
                class="aspect-square animate-pulse rounded-lg bg-gray-200 dark:bg-gray-700"
            ></div>
        </div>
        <div v-else-if="photos.length === 0" class="py-12 text-center">
            <p class="text-sm text-gray-400 dark:text-gray-500">
                Nessuna foto caricata
            </p>
        </div>
        <div v-else class="grid grid-cols-3 gap-3">
            <button
                v-for="photo in photos"
                :key="photo.id"
                :data-testid="`photo-${photo.id}`"
                class="group relative aspect-square overflow-hidden rounded-lg border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800"
                @click="emit('view', photo)"
            >
                <img
                    :src="`/storage/${photo.thumbnail_key || photo.storage_key}`"
                    :alt="`Foto ${formatDate(photo.taken_at)}`"
                    class="h-full w-full object-cover transition-transform group-hover:scale-105"
                    loading="lazy"
                />
                <div
                    class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/60 to-transparent p-2"
                >
                    <p class="text-xs text-white">
                        {{ formatDate(photo.taken_at) }}
                    </p>
                </div>
            </button>
        </div>
    </div>
</template>
