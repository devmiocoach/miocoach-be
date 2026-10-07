<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    coachId: number;
    modelValue: string | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [string | null];
}>();

const today = new Date();
const currentMonth = ref(today.getFullYear() * 12 + today.getMonth());
const availableDates = ref<string[]>([]);
const loading = ref(false);

const year = computed(() => Math.floor(currentMonth.value / 12));
const month = computed(() => currentMonth.value % 12);

const monthLabel = computed(() => {
    return new Date(year.value, month.value).toLocaleDateString('it-IT', {
        month: 'long',
        year: 'numeric',
    });
});

const days = computed(() => {
    const firstDay = new Date(year.value, month.value, 1);
    const lastDay = new Date(year.value, month.value + 1, 0);
    const startOffset = (firstDay.getDay() + 6) % 7;
    const result: {
        date: string;
        day: number;
        available: boolean;
        past: boolean;
        inMonth: boolean;
    }[] = [];

    for (let i = 0; i < startOffset; i++) {
        result.push({
            date: '',
            day: 0,
            available: false,
            past: true,
            inMonth: false,
        });
    }

    for (let d = 1; d <= lastDay.getDate(); d++) {
        const dateStr = `${year.value}-${String(month.value + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        const dateObj = new Date(year.value, month.value, d);
        const isPast =
            dateObj <
            new Date(today.getFullYear(), today.getMonth(), today.getDate());
        result.push({
            date: dateStr,
            day: d,
            available: availableDates.value.includes(dateStr),
            past: isPast,
            inMonth: true,
        });
    }

    return result;
});

const fetchAvailableDates = async () => {
    loading.value = true;
    const monthStr = `${year.value}-${String(month.value + 1).padStart(2, '0')}`;
    try {
        const response = await fetch(
            `/api/v1/coaches/${props.coachId}/available-dates?month=${monthStr}`,
        );
        if (response.ok) {
            const json = await response.json();
            availableDates.value = json.data ?? [];
        }
    } finally {
        loading.value = false;
    }
};

const prevMonth = () => {
    const minMonth = today.getFullYear() * 12 + today.getMonth();
    if (currentMonth.value > minMonth) {
        currentMonth.value--;
    }
};

const nextMonth = () => {
    currentMonth.value++;
};

const selectDate = (date: string) => {
    emit('update:modelValue', date);
};

watch(currentMonth, fetchAvailableDates, { immediate: true });
</script>

<template>
    <div>
        <!-- Header -->
        <div class="mb-4 flex items-center justify-between">
            <button
                type="button"
                class="rounded-lg p-2 hover:bg-gray-100 dark:hover:bg-gray-800"
                @click="prevMonth"
            >
                <ChevronLeft
                    :size="18"
                    class="text-gray-600 dark:text-gray-400"
                />
            </button>
            <h3
                class="text-sm font-semibold text-gray-900 capitalize dark:text-white"
            >
                {{ monthLabel }}
            </h3>
            <button
                type="button"
                class="rounded-lg p-2 hover:bg-gray-100 dark:hover:bg-gray-800"
                @click="nextMonth"
            >
                <ChevronRight
                    :size="18"
                    class="text-gray-600 dark:text-gray-400"
                />
            </button>
        </div>

        <!-- Day labels -->
        <div class="mb-2 grid grid-cols-7 gap-1">
            <span
                v-for="label in [
                    'Lun',
                    'Mar',
                    'Mer',
                    'Gio',
                    'Ven',
                    'Sab',
                    'Dom',
                ]"
                :key="label"
                class="text-center text-xs font-medium text-gray-400 dark:text-gray-500"
            >
                {{ label }}
            </span>
        </div>

        <!-- Days grid -->
        <div class="grid grid-cols-7 gap-1">
            <button
                v-for="(d, i) in days"
                :key="i"
                type="button"
                :disabled="!d.inMonth || d.past || !d.available"
                class="h-10 rounded-lg text-sm font-medium transition-colors"
                :class="{
                    'pointer-events-none opacity-0': !d.inMonth,
                    'cursor-not-allowed text-gray-300 dark:text-gray-700':
                        d.inMonth && (d.past || !d.available),
                    'text-gray-900 hover:bg-teal-50 dark:text-white dark:hover:bg-teal-950':
                        d.inMonth &&
                        !d.past &&
                        d.available &&
                        d.date !== modelValue,
                    'bg-teal-500 text-white': d.date === modelValue,
                }"
                @click="d.available && selectDate(d.date)"
            >
                {{ d.day || '' }}
            </button>
        </div>

        <div v-if="loading" class="mt-3 text-center text-xs text-gray-400">
            Caricamento...
        </div>
    </div>
</template>
