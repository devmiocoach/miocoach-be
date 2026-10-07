<script setup lang="ts">
import { METRICS, type MetricType } from '../types';

defineProps<{
    selected: MetricType;
}>();

const emit = defineEmits<{
    select: [metric: MetricType];
}>();
</script>

<template>
    <div class="flex flex-wrap gap-2" data-testid="metric-pills">
        <button
            v-for="metric in METRICS"
            :key="metric.value"
            :data-testid="`metric-pill-${metric.value}`"
            class="rounded-full border px-3 py-1.5 text-sm font-medium transition-colors"
            :class="
                selected === metric.value
                    ? 'border-primary bg-primary text-white'
                    : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-600'
            "
            @click="emit('select', metric.value)"
        >
            {{ metric.label }}
        </button>
    </div>
</template>
