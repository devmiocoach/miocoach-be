<script setup lang="ts">
import type { ChartSummary } from '../types';

defineProps<{
    summary: ChartSummary | null;
    unit: string;
}>();
</script>

<template>
    <div
        v-if="summary"
        class="grid grid-cols-2 gap-4 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-4 dark:border-gray-700 dark:bg-gray-900"
        data-testid="measurement-summary"
    >
        <div>
            <p class="text-xs text-gray-500 dark:text-gray-400">Variazione</p>
            <p
                class="text-lg font-semibold"
                :class="
                    summary.delta < 0
                        ? 'text-green-600 dark:text-green-400'
                        : summary.delta > 0
                          ? 'text-red-500 dark:text-red-400'
                          : 'text-gray-900 dark:text-white'
                "
            >
                {{ summary.delta > 0 ? '+' : '' }}{{ summary.delta }}
                {{ unit }}
                <span class="text-xs font-normal">
                    ({{ summary.delta_percent > 0 ? '+' : ''
                    }}{{ summary.delta_percent }}%)
                </span>
            </p>
        </div>
        <div>
            <p class="text-xs text-gray-500 dark:text-gray-400">Ultimo</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ summary.last_value }} {{ unit }}
            </p>
        </div>
        <div>
            <p class="text-xs text-gray-500 dark:text-gray-400">Min</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ summary.min }} {{ unit }}
            </p>
        </div>
        <div>
            <p class="text-xs text-gray-500 dark:text-gray-400">Max</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ summary.max }} {{ unit }}
            </p>
        </div>
    </div>
</template>
