<script setup lang="ts">
import { computed } from 'vue';
import type { AvailabilitySlot } from '../../../types/public';

const props = defineProps<{
    availability: AvailabilitySlot[];
}>();

const dayLabels = ['Dom', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab'];

const grouped = computed(() => {
    const map = new Map<number, AvailabilitySlot[]>();
    for (const slot of props.availability) {
        if (!map.has(slot.day_of_week)) {
            map.set(slot.day_of_week, []);
        }
        map.get(slot.day_of_week)!.push(slot);
    }
    return [1, 2, 3, 4, 5, 6, 0].map((day) => ({
        day,
        label: dayLabels[day],
        slots: map.get(day) ?? [],
    }));
});

const formatTime = (time: string) => time.slice(0, 5);
</script>

<template>
    <section v-if="availability.length" class="py-8">
        <h2 class="mb-4 text-xl font-bold text-gray-900 dark:text-white">
            Disponibilità
        </h2>
        <div class="grid grid-cols-7 gap-2">
            <div v-for="day in grouped" :key="day.day" class="text-center">
                <p
                    class="mb-2 text-xs font-semibold text-gray-500 dark:text-gray-400"
                >
                    {{ day.label }}
                </p>
                <div v-if="day.slots.length" class="space-y-1">
                    <div
                        v-for="slot in day.slots"
                        :key="slot.id"
                        class="rounded bg-teal-50 px-1 py-1.5 text-[10px] font-medium text-teal-700 dark:bg-teal-950 dark:text-teal-300"
                    >
                        {{ formatTime(slot.start_time) }}
                    </div>
                </div>
                <div v-else class="py-2">
                    <div
                        class="h-6 w-full rounded bg-gray-100 dark:bg-gray-800"
                    />
                </div>
            </div>
        </div>
    </section>
</template>
