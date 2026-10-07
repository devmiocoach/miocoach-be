<script setup lang="ts">
import { computed, ref } from 'vue';

import IconChevronLeft from '~icons/heroicons/chevron-left-20-solid';
import IconChevronRight from '~icons/heroicons/chevron-right-20-solid';

import type { AgendaBooking } from '@modules/Users/resources/js/types/agenda';

const props = defineProps<{
    bookings: AgendaBooking[];
    selectedDate: Date;
}>();

const emit = defineEmits<{
    dateClick: [date: Date];
}>();

const displayMonth = ref(new Date(props.selectedDate));

const monthLabel = computed(() =>
    displayMonth.value.toLocaleDateString('it-IT', {
        month: 'long',
        year: 'numeric',
    }),
);

const calendarDays = computed(() => {
    const year = displayMonth.value.getFullYear();
    const month = displayMonth.value.getMonth();
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);

    let startDow = firstDay.getDay();
    if (startDow === 0) startDow = 7;

    const days: Array<{ date: Date; inMonth: boolean }> = [];

    for (let i = 1 - startDow + 1; i <= lastDay.getDate(); i++) {
        if (i < 1) {
            const d = new Date(year, month, i);
            days.push({ date: d, inMonth: false });
        } else {
            days.push({ date: new Date(year, month, i), inMonth: true });
        }
    }

    while (days.length % 7 !== 0) {
        const next = new Date(
            year,
            month + 1,
            days.length - lastDay.getDate() - (startDow - 2),
        );
        days.push({ date: next, inMonth: false });
    }

    return days;
});

const bookingDates = computed(() => {
    const dates = new Set<string>();
    for (const b of props.bookings) {
        dates.add(b.starts_at.split('T')[0]);
    }
    return dates;
});

function hasBookings(date: Date): boolean {
    const iso = date.toISOString().split('T')[0];
    return bookingDates.value.has(iso);
}

function isSelected(date: Date): boolean {
    return date.toDateString() === props.selectedDate.toDateString();
}

function isToday(date: Date): boolean {
    return date.toDateString() === new Date().toDateString();
}

function prevMonth() {
    const d = new Date(displayMonth.value);
    d.setMonth(d.getMonth() - 1);
    displayMonth.value = d;
}

function nextMonth() {
    const d = new Date(displayMonth.value);
    d.setMonth(d.getMonth() + 1);
    displayMonth.value = d;
}

const weekdayLabels = ['L', 'M', 'M', 'G', 'V', 'S', 'D'];
</script>

<template>
    <div
        class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900"
        data-testid="mini-calendar"
    >
        <!-- Month nav -->
        <div class="mb-2 flex items-center justify-between">
            <button
                class="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                @click="prevMonth"
            >
                <IconChevronLeft class="size-4" />
            </button>
            <span
                class="text-xs font-medium text-gray-700 capitalize dark:text-gray-300"
            >
                {{ monthLabel }}
            </span>
            <button
                class="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                @click="nextMonth"
            >
                <IconChevronRight class="size-4" />
            </button>
        </div>

        <!-- Weekday headers -->
        <div class="grid grid-cols-7 text-center">
            <div
                v-for="label in weekdayLabels"
                :key="label"
                class="py-1 text-[10px] font-medium text-gray-400 dark:text-gray-500"
            >
                {{ label }}
            </div>
        </div>

        <!-- Days grid -->
        <div class="grid grid-cols-7">
            <button
                v-for="(d, i) in calendarDays"
                :key="i"
                :class="[
                    'relative flex size-7 items-center justify-center rounded-full text-[11px] transition-colors',
                    d.inMonth
                        ? 'text-gray-700 dark:text-gray-300'
                        : 'text-gray-300 dark:text-gray-600',
                    isSelected(d.date)
                        ? 'bg-primary text-white dark:text-white'
                        : '',
                    isToday(d.date) && !isSelected(d.date)
                        ? 'text-primary font-bold'
                        : '',
                    !isSelected(d.date)
                        ? 'hover:bg-gray-100 dark:hover:bg-gray-800'
                        : '',
                ]"
                @click="emit('dateClick', d.date)"
            >
                {{ d.date.getDate() }}
                <span
                    v-if="hasBookings(d.date) && !isSelected(d.date)"
                    class="bg-primary absolute bottom-0.5 left-1/2 size-1 -translate-x-1/2 rounded-full"
                />
            </button>
        </div>
    </div>
</template>
