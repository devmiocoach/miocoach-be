<script setup lang="ts">
import type { PasswordStrength } from '@/composables/usePasswordStrength';
import { computed } from 'vue';
import IconShieldAlert from '~icons/lucide/shield-alert';
import IconShieldCheck from '~icons/lucide/shield-check';
import IconShieldEllipsis from '~icons/lucide/shield-ellipsis';
import IconShieldHalf from '~icons/lucide/shield-half';
import IconShieldOff from '~icons/lucide/shield-off';

const props = defineProps<{
    strength: PasswordStrength;
}>();

const segments = computed(() => {
    return Array.from({ length: 5 }, (_, i) => ({
        active: i < props.strength.score,
        color:
            i < props.strength.score
                ? props.strength.bgColor
                : 'bg-gray-200 dark:bg-gray-700',
    }));
});

const icon = computed(() => {
    if (props.strength.score <= 1) return IconShieldOff;
    if (props.strength.score === 2) return IconShieldAlert;
    if (props.strength.score === 3) return IconShieldEllipsis;
    if (props.strength.score === 4) return IconShieldHalf;
    return IconShieldCheck;
});
</script>

<template>
    <div class="mt-2 space-y-1.5">
        <div class="flex gap-1">
            <div
                v-for="(segment, i) in segments"
                :key="i"
                class="h-1 flex-1 rounded-full transition-colors duration-300"
                :class="segment.color"
            />
        </div>
        <div class="flex items-center gap-1.5" :class="strength.color">
            <component :is="icon" class="h-3.5 w-3.5" />
            <span class="text-xs font-medium">{{ strength.label }}</span>
        </div>
    </div>
</template>
