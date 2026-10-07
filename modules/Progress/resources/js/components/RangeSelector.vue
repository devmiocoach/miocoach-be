<script setup lang="ts">
import { RANGES, type ChartRange } from '../types';

defineProps<{
    selected: ChartRange;
}>();

const emit = defineEmits<{
    change: [range: ChartRange];
}>();
</script>

<template>
    <div
        class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-0.5 dark:border-gray-700 dark:bg-gray-800"
        data-testid="range-selector"
    >
        <button
            v-for="r in RANGES"
            :key="r.value"
            :data-testid="`range-${r.value}`"
            class="rounded-md px-3 py-1 text-xs font-medium transition-colors"
            :class="
                selected === r.value
                    ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                    : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
            "
            @click="emit('change', r.value)"
        >
            {{ r.label }}
        </button>
    </div>
</template>
