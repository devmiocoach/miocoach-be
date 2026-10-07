<script setup lang="ts">
import type { TimeSlotData } from '../../../types/public';
import BookingCalendar from './BookingCalendar.vue';
import TimeSlotGrid from './TimeSlotGrid.vue';

defineProps<{
    coachId: number;
    selectedDate: string | null;
    selectedSlot: TimeSlotData | null;
}>();

const emit = defineEmits<{
    'update:date': [string | null];
    'update:slot': [TimeSlotData | null];
}>();
</script>

<template>
    <div>
        <h2 class="mb-2 text-xl font-bold text-gray-900 dark:text-white">
            Scegli data e ora
        </h2>
        <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">
            I giorni verdi hanno slot disponibili
        </p>

        <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
            <BookingCalendar
                :coach-id="coachId"
                :model-value="selectedDate"
                @update:model-value="
                    emit('update:date', $event);
                    emit('update:slot', null);
                "
            />

            <TimeSlotGrid
                :coach-id="coachId"
                :date="selectedDate"
                :model-value="selectedSlot"
                @update:model-value="emit('update:slot', $event)"
            />
        </div>
    </div>
</template>
