<script setup lang="ts">
import { ref, watch } from 'vue';
import type { TimeSlotData } from '../../../types/public';

const props = defineProps<{
    coachId: number;
    date: string | null;
    modelValue: TimeSlotData | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [TimeSlotData | null];
}>();

const slots = ref<TimeSlotData[]>([]);
const loading = ref(false);

const fetchSlots = async () => {
    if (!props.date) {
        slots.value = [];
        return;
    }

    loading.value = true;
    try {
        const response = await fetch(
            `/api/v1/coaches/${props.coachId}/slots?date=${props.date}`,
        );
        if (response.ok) {
            const json = await response.json();
            slots.value = json.data ?? [];
        }
    } finally {
        loading.value = false;
    }
};

const formatTime = (iso: string) => {
    return new Date(iso).toLocaleTimeString('it-IT', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const selectSlot = (slot: TimeSlotData) => {
    emit('update:modelValue', slot);
};

watch(() => props.date, fetchSlots, { immediate: true });
</script>

<template>
    <div v-if="date">
        <h4 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">
            Orari disponibili
        </h4>

        <div v-if="loading" class="grid grid-cols-3 gap-2 sm:grid-cols-4">
            <div
                v-for="i in 6"
                :key="i"
                class="h-10 animate-pulse rounded-lg bg-gray-100 dark:bg-gray-800"
            />
        </div>

        <div
            v-else-if="slots.length"
            class="grid grid-cols-3 gap-2 sm:grid-cols-4"
        >
            <button
                v-for="slot in slots"
                :key="slot.starts_at"
                type="button"
                class="rounded-lg px-3 py-2.5 text-sm font-medium transition-colors"
                :class="
                    modelValue?.starts_at === slot.starts_at
                        ? 'bg-teal-500 text-white'
                        : 'bg-gray-100 text-gray-700 hover:bg-teal-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-teal-950'
                "
                @click="selectSlot(slot)"
            >
                {{ formatTime(slot.starts_at) }}
            </button>
        </div>

        <p v-else class="text-sm text-gray-500 dark:text-gray-400">
            Nessun orario disponibile per questa data.
        </p>

        <p
            v-if="modelValue"
            class="mt-3 text-xs text-gray-500 dark:text-gray-400"
        >
            Durata: {{ modelValue.duration_minutes }} minuti
        </p>
    </div>
</template>
