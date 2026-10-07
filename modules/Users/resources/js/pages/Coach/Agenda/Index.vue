<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import CoachLayout from '@/layouts/CoachLayout.vue';
import { useAgendaKeyboard } from '@modules/Users/resources/js/composables/useAgendaKeyboard';
import { useAgendaNavigation } from '@modules/Users/resources/js/composables/useAgendaNavigation';
import { computed, ref, toRef } from 'vue';

import IconChevronLeft from '~icons/heroicons/chevron-left-20-solid';
import IconChevronRight from '~icons/heroicons/chevron-right-20-solid';

import type { AgendaProps } from '@modules/Users/resources/js/types/agenda';

import DayView from './DayView.vue';
import EditBookingDialog from './EditBookingDialog.vue';
import MiniCalendar from './MiniCalendar.vue';
import MonthView from './MonthView.vue';
import NewBookingDialog from './NewBookingDialog.vue';
import ScheduleView from './ScheduleView.vue';
import WeekView from './WeekView.vue';

import type { AgendaBooking } from '@modules/Users/resources/js/types/agenda';

const props = defineProps<AgendaProps>();

const {
    currentWeekStart,
    currentDay,
    viewMode,
    weekDays,
    nextWeek,
    prevWeek,
    nextDay,
    prevDay,
    goToToday,
    goToDate,
    goToDayView,
} = useAgendaNavigation(toRef(props, 'weekStart'));

const showNewBooking = ref(false);
const newBookingDefaults = ref<{ date?: string; time?: string }>({});
const selectedBooking = ref<AgendaBooking | null>(null);

function openNewBooking(date?: string, time?: string) {
    newBookingDefaults.value = { date, time };
    showNewBooking.value = true;
}

function openBookingDetail(booking: AgendaBooking) {
    selectedBooking.value = booking;
}

function handleMiniCalendarClick(date: Date) {
    goToDayView(date);
}

useAgendaKeyboard({
    goToToday,
    openNewBooking: () => openNewBooking(),
    nextPeriod: handleNext,
    prevPeriod: handlePrev,
    setView: (v) => {
        viewMode.value = v;
    },
});

function handlePrev() {
    if (viewMode.value === 'day') prevDay();
    else prevWeek();
}

function handleNext() {
    if (viewMode.value === 'day') nextDay();
    else nextWeek();
}

const navigationLabel = computed(() => {
    if (viewMode.value === 'day') {
        return currentDay.value.toLocaleDateString('it-IT', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    }
    if (weekDays.value.length < 7) return '';
    const start = weekDays.value[0];
    const end = weekDays.value[6];
    const opts: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'short' };
    const startStr = start.toLocaleDateString('it-IT', opts);
    const endStr = end.toLocaleDateString('it-IT', {
        ...opts,
        year: 'numeric',
    });
    return `${startStr} — ${endStr}`;
});
</script>

<template>
    <CoachLayout
        :title="$t('Agenda')"
        :breadcrumbs="[
            { title: $t('Dashboard'), url: route('coach.dashboard') },
            { title: $t('Agenda') },
        ]"
    >
        <div class="flex flex-1 flex-col gap-4 p-6 pt-2">
            <!-- Header -->
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                data-testid="agenda-header"
            >
                <div class="flex items-center gap-3">
                    <h1
                        class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white"
                    >
                        {{ $t('Agenda') }}
                    </h1>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Navigation -->
                    <div class="flex items-center gap-1">
                        <Button
                            variant="outline"
                            size="icon"
                            data-testid="agenda-prev"
                            @click="handlePrev"
                        >
                            <IconChevronLeft class="size-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            data-testid="agenda-today"
                            @click="goToToday"
                        >
                            {{ $t('Today') }}
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            data-testid="agenda-next"
                            @click="handleNext"
                        >
                            <IconChevronRight class="size-4" />
                        </Button>
                    </div>

                    <!-- Date label -->
                    <span
                        class="hidden text-sm font-medium text-gray-600 sm:inline dark:text-gray-400"
                    >
                        {{ navigationLabel }}
                    </span>

                    <!-- View toggle -->
                    <div
                        class="ml-2 flex rounded-lg border border-gray-200 dark:border-gray-700"
                    >
                        <button
                            :class="[
                                'px-3 py-1.5 text-xs font-medium transition-colors',
                                viewMode === 'day'
                                    ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                                    : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white',
                            ]"
                            class="rounded-l-md"
                            data-testid="agenda-view-day"
                            @click="viewMode = 'day'"
                        >
                            {{ $t('Day') }}
                        </button>
                        <button
                            :class="[
                                'px-3 py-1.5 text-xs font-medium transition-colors',
                                viewMode === 'week'
                                    ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                                    : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white',
                            ]"
                            data-testid="agenda-view-week"
                            @click="viewMode = 'week'"
                        >
                            {{ $t('Week') }}
                        </button>
                        <button
                            :class="[
                                'px-3 py-1.5 text-xs font-medium transition-colors',
                                viewMode === 'month'
                                    ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                                    : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white',
                            ]"
                            data-testid="agenda-view-month"
                            @click="viewMode = 'month'"
                        >
                            {{ $t('Month') }}
                        </button>
                        <button
                            :class="[
                                'px-3 py-1.5 text-xs font-medium transition-colors',
                                viewMode === 'schedule'
                                    ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                                    : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white',
                            ]"
                            class="rounded-r-md"
                            data-testid="agenda-view-schedule"
                            @click="viewMode = 'schedule'"
                        >
                            {{ $t('Schedule') }}
                        </button>
                    </div>

                    <!-- New booking -->
                    <Button
                        size="sm"
                        data-testid="agenda-new-booking"
                        @click="openNewBooking()"
                    >
                        + {{ $t('New booking') }}
                    </Button>
                </div>
            </div>

            <!-- Main content with sidebar -->
            <div class="flex flex-1 gap-4">
                <!-- Mini calendar sidebar (desktop only) -->
                <div class="hidden w-52 shrink-0 lg:block">
                    <MiniCalendar
                        :bookings="bookings"
                        :selected-date="currentDay"
                        @date-click="handleMiniCalendarClick"
                    />
                </div>

                <!-- Views -->
                <div class="min-w-0 flex-1">
                    <DayView
                        v-if="viewMode === 'day'"
                        :bookings="bookings"
                        :day="currentDay"
                        :availabilities="availabilities"
                        @slot-click="openNewBooking($event.date, $event.time)"
                        @booking-click="openBookingDetail"
                    />
                    <WeekView
                        v-else-if="viewMode === 'week'"
                        :bookings="bookings"
                        :week-days="weekDays"
                        :availabilities="availabilities"
                        @slot-click="openNewBooking($event.date, $event.time)"
                        @booking-click="openBookingDetail"
                    />
                    <MonthView
                        v-else-if="viewMode === 'month'"
                        :bookings="bookings"
                        :current-week-start="currentWeekStart"
                        @day-click="goToDate($event)"
                    />
                    <ScheduleView
                        v-else
                        :bookings="bookings"
                        :week-days="weekDays"
                        @booking-click="openBookingDetail"
                    />
                </div>
            </div>

            <!-- Dialogs -->
            <NewBookingDialog
                v-model:open="showNewBooking"
                :clients="clients"
                :service-types="serviceTypes"
                :default-duration="defaultDuration"
                :defaults="newBookingDefaults"
            />
            <EditBookingDialog
                :booking="selectedBooking"
                :service-types="serviceTypes"
                @close="selectedBooking = null"
            />
        </div>
    </CoachLayout>
</template>
