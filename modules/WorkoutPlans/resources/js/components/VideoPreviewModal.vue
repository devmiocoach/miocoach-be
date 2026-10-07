<script setup lang="ts">
import Dialog from '@/components/ui/dialog/Dialog.vue';
import DialogContent from '@/components/ui/dialog/DialogContent.vue';
import { computed } from 'vue';

const props = defineProps<{
    url: string | null;
    open: boolean;
}>();

defineEmits<{ close: [] }>();

/**
 * Build a provider embed URL. The stored url already passed the
 * AllowedVideoHost rule server-side, so only YouTube and Vimeo reach here.
 */
const embedUrl = computed(() => {
    if (!props.url) {
        return null;
    }

    const parsed = new URL(props.url);

    if (parsed.hostname === 'youtu.be') {
        return `https://www.youtube.com/embed${parsed.pathname}`;
    }

    if (parsed.hostname.endsWith('youtube.com')) {
        const id = parsed.searchParams.get('v');
        return id ? `https://www.youtube.com/embed/${id}` : null;
    }

    if (parsed.hostname.endsWith('vimeo.com')) {
        const id = parsed.pathname.split('/').filter(Boolean).pop();
        return id ? `https://player.vimeo.com/video/${id}` : null;
    }

    return null;
});
</script>

<template>
    <Dialog :open="open" @update:open="$emit('close')">
        <DialogContent
            class="max-w-3xl bg-white dark:bg-gray-900"
            data-testid="video-preview-modal"
        >
            <div v-if="embedUrl" class="aspect-video w-full">
                <iframe
                    :src="embedUrl"
                    class="h-full w-full rounded-lg"
                    allowfullscreen
                    data-testid="video-preview-iframe"
                />
            </div>
            <p
                v-else
                class="py-12 text-center text-gray-600 dark:text-gray-400"
                data-testid="video-preview-empty"
            >
                {{ $t('No video available') }}
            </p>
        </DialogContent>
    </Dialog>
</template>
