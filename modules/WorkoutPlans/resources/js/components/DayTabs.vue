<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import IconPlus from '~icons/heroicons/plus';
import IconXMark from '~icons/heroicons/x-mark';
import type { BuilderDay } from '../composables/usePlanBuilder';

defineProps<{
    days: BuilderDay[];
    activeIndex: number;
}>();

defineEmits<{
    select: [index: number];
    add: [];
    remove: [index: number];
}>();
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-2 border-b border-gray-200 pb-2 dark:border-gray-800"
    >
        <button
            v-for="(day, index) in days"
            :key="index"
            type="button"
            class="group flex items-center gap-2 rounded-md px-3 py-1.5 text-sm transition"
            :class="
                index === activeIndex
                    ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800'
            "
            :data-testid="`day-tab-${day.dayNumber}`"
            @click="$emit('select', index)"
        >
            {{ day.name || `${$t('Day')} ${day.dayNumber}` }}
            <span
                class="opacity-0 group-hover:opacity-100"
                :data-testid="`remove-day-${day.dayNumber}`"
                @click.stop="$emit('remove', index)"
            >
                <IconXMark class="h-3 w-3" />
            </span>
        </button>

        <Button
            variant="ghost"
            size="sm"
            data-testid="add-day-btn"
            @click="$emit('add')"
        >
            <IconPlus class="mr-1 h-4 w-4" />
            {{ $t('Add day') }}
        </Button>
    </div>
</template>
