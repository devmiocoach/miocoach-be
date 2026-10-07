<script setup lang="ts">
const model = defineModel<string | null>({ default: null });

const options = [
    { value: '', label: 'No repeat' },
    { value: 'weekly', label: 'Every week' },
    { value: 'biweekly', label: 'Every 2 weeks' },
    { value: 'monthly', label: 'Every month' },
];
</script>

<template>
    <div>
        <label
            class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400"
        >
            {{ $t('Repeat') }}
        </label>
        <select
            :value="model || ''"
            class="w-full rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
            data-testid="recurrence-select"
            @change="model = ($event.target as HTMLSelectElement).value || null"
        >
            <option v-for="opt in options" :key="opt.value" :value="opt.value">
                {{ $t(opt.label) }}
            </option>
        </select>
    </div>
</template>
