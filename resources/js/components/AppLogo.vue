<script setup lang="ts">
defineProps<{
    size?: 'sm' | 'md' | 'lg' | 'xl' | 'xxl';
    showText?: boolean;
    centered?: boolean;
    variant?: 'brand' | 'light';
    showSubtitle?: boolean;
    subtitleSize?: 'xs' | 'sm' | 'md' | 'xl' | 'xxl';
}>();

const iconSizeClasses: Record<string, string> = {
    sm: 'h-8 w-8',
    md: 'h-10 w-10',
    lg: 'h-12 w-12',
    xl: 'h-16 w-16',
    xxl: 'h-24 w-24',
};

const extendedHeightClasses: Record<string, string> = {
    sm: 'h-7',
    md: 'h-9',
    lg: 'h-11',
    xl: 'h-14',
    xxl: 'h-20',
};
</script>

<template>
    <div :class="centered ? 'flex flex-col items-center' : 'flex items-center'">
        <img
            v-if="showText"
            src="/images/logo-extended.svg"
            alt="MioCoach"
            :class="[
                extendedHeightClasses[size ?? 'md'],
                'w-auto',
                variant === 'light' && 'brightness-0 invert',
            ]"
        />
        <img
            v-else
            src="/images/logo.svg"
            alt="MioCoach"
            :class="[
                iconSizeClasses[size ?? 'md'],
                variant === 'light' && 'brightness-0 invert',
            ]"
        />
    </div>
</template>
