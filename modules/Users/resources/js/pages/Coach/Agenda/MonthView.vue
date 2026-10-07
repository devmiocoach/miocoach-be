<script setup lang="ts">
import { computed } from 'vue';

import type { AgendaBooking } from '@modules/Users/resources/js/types/agenda';

const props = defineProps<{
    bookings: AgendaBooking[];
    currentWeekStart: string;
}>();

const emit = defineEmits<{
    dayClick: [date: Date];
}>();

const SERVICE_DOT_COLORS: Record<string, string> = {
    personal_training: 'bg-blue-500',
    online_coaching: 'bg-indigo-500',
    assessment: 'bg-amber-500',
    group_session: 'bg-green-500',
    nutrition_consultation: 'bg-purple-500',
};

const monthData = computed(() => {
    const start = new Date(props.currentWeekStart);
    const month = start.getMonth();
    const year = start.getFullYear();

    const firstDay = new Date(year, month, 1);
    const startOffset = (firstDay.getDay() + 6) % 7;
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    const weeks: Array<Array<{ date: Date | null; day: number | null }>> = [];
    let currentWeek: Array<{ date: Date | null; day: number | null }> = [];

    for (let i = 0; i < startOffset; i++) {
        currentWeek.push({ date: null, day: null });
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(year, month, day);
        currentWeek.push({ date, day });
        if (currentWeek.length === 7) {
            weeks.push(currentWeek);
            currentWeek = [];
        }
    }

    while (currentWeek.length > 0 && currentWeek.length < 7) {
        currentWeek.push({ date: null, day: null });
    }
    if (currentWeek.length > 0) {
        weeks.push(currentWeek);
    }

    return weeks;
});

function bookingsForDate(date: Date | null): AgendaBooking[] {
    if (!date) return [];
    const dayStr = date.toISOString().split('T')[0];
    return props.bookings.filter((b) => b.starts_at.startsWith(dayStr));
}

function isToday(date: Date | null): boolean {
    if (!date) return false;
    return date.toDateString() === new Date().toDateString();
}

function getDotColor(serviceType: string | null): string {
    if (!serviceType) return 'bg-gray-400';
    return SERVICE_DOT_COLORS[serviceType] || 'bg-gray-400';
}

const dayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
</script>

<template>
    <div
        class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
        data-testid="agenda-month-view"
    >
        <!-- Day labels -->
        <div
            class="grid grid-cols-7 border-b border-gray-200 dark:border-gray-800"
        >
            <div
                v-for="label in dayLabels"
                :key="label"
                class="p-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400"
            >
                {{ $t(label) }}
            </div>
        </div>

        <!-- Weeks -->
        <div
            v-for="(week, wIdx) in monthData"
            :key="wIdx"
            class="grid grid-cols-7 border-b border-gray-100 last:border-b-0 dark:border-gray-800/50"
        >
            <div
                v-for="(cell, dIdx) in week"
                :key="dIdx"
                :class="[
                    'min-h-[5rem] cursor-pointer border-r border-gray-100 p-1.5 transition-colors last:border-r-0 dark:border-gray-800/50',
                    cell.date
                        ? 'hover:bg-gray-50 dark:hover:bg-gray-800/50'
                        : '',
                    isToday(cell.date) ? 'bg-primary/5' : '',
                ]"
                @click="cell.date && emit('dayClick', cell.date)"
            >
                <span
                    v-if="cell.day"
                    :class="[
                        'inline-flex size-6 items-center justify-center rounded-full text-xs',
                        isToday(cell.date)
                            ? 'bg-primary text-primary-foreground font-bold'
                            : 'text-gray-700 dark:text-gray-300',
                    ]"
                >
                    {{ cell.day }}
                </span>

                <!-- Dots for bookings -->
                <div
                    v-if="bookingsForDate(cell.date).length > 0"
                    class="mt-1 flex flex-wrap gap-0.5"
                >
                    <span
                        v-for="(b, i) in bookingsForDate(cell.date).slice(0, 4)"
                        :key="i"
                        :class="[
                            'inline-block size-1.5 rounded-full',
                            getDotColor(b.service_type),
                        ]"
                    />
                    <span
                        v-if="bookingsForDate(cell.date).length > 4"
                        class="text-[9px] text-gray-400"
                    >
                        +{{ bookingsForDate(cell.date).length - 4 }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
