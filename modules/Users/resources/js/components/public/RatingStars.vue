<script setup lang="ts">
import { Star } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    rating: number | null;
    size?: number;
}>();

const stars = computed(() => {
    const r = props.rating ?? 0;
    return Array.from({ length: 5 }, (_, i) => {
        const fill = Math.min(1, Math.max(0, r - i));
        return fill >= 0.75 ? 'full' : fill >= 0.25 ? 'half' : 'empty';
    });
});
</script>

<template>
    <div class="flex items-center gap-0.5">
        <template v-for="(star, i) in stars" :key="i">
            <div
                class="relative"
                :style="{ width: `${size ?? 16}px`, height: `${size ?? 16}px` }"
            >
                <Star
                    :size="size ?? 16"
                    class="text-gray-200 dark:text-gray-700"
                    :fill="'currentColor'"
                />
                <div
                    v-if="star !== 'empty'"
                    class="absolute inset-0 overflow-hidden"
                    :style="{ width: star === 'full' ? '100%' : '50%' }"
                >
                    <Star
                        :size="size ?? 16"
                        class="text-amber-400"
                        :fill="'currentColor'"
                    />
                </div>
            </div>
        </template>
    </div>
</template>
