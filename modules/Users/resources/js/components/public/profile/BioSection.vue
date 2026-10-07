<script setup lang="ts">
import type { PublicCoachData } from '../../../types/public';

defineProps<{
    coach: PublicCoachData;
}>();
</script>

<template>
    <section v-if="coach.bio" class="py-8">
        <h2 class="mb-4 text-xl font-bold text-gray-900 dark:text-white">
            Chi sono
        </h2>
        <div
            class="prose prose-gray dark:prose-invert max-w-none whitespace-pre-line text-gray-600 dark:text-gray-400"
        >
            {{ coach.bio }}
        </div>
        <div
            v-if="coach.intro_video_url"
            class="mt-6 aspect-video overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800"
        >
            <iframe
                :src="coach.intro_video_url"
                class="h-full w-full"
                frameborder="0"
                allow="
                    accelerometer;
                    autoplay;
                    clipboard-write;
                    encrypted-media;
                    gyroscope;
                    picture-in-picture;
                "
                allowfullscreen
            />
        </div>
    </section>
</template>
