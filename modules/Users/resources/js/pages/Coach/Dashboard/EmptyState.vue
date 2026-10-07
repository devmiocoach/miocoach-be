<script setup lang="ts">
import IconSun from '~icons/heroicons/sun';
</script>

<template>
    <div
        class="flex flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 px-6 py-12 text-center dark:border-gray-700"
        data-testid="empty-state-today"
    >
        <div
            class="mb-4 flex size-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30"
        >
            <IconSun class="size-8 text-amber-600 dark:text-amber-400" />
        </div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ $t('No sessions today') }}
        </h3>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ $t('Time to rest or plan ahead!') }}
        </p>
    </div>
</template>
