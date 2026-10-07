import { router } from '@inertiajs/vue3';
import { computed, ref, type Ref, watch } from 'vue';

export function useAgendaNavigation(weekStartProp: Ref<string> | string) {
    const initial =
        typeof weekStartProp === 'string' ? weekStartProp : weekStartProp.value;
    const currentWeekStart = ref(initial);
    const viewMode = ref<'day' | 'week' | 'month' | 'schedule'>('week');
    const currentDay = ref(new Date());

    if (typeof weekStartProp !== 'string') {
        watch(weekStartProp, (val) => {
            currentWeekStart.value = val;
        });
    }

    function getMonday(date: Date): Date {
        const d = new Date(date);
        const day = d.getDay();
        const diff = d.getDate() - day + (day === 0 ? -6 : 1);
        d.setDate(diff);
        d.setHours(0, 0, 0, 0);
        return d;
    }

    function toDateString(date: Date): string {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function navigate(weekDate: string) {
        router.reload({
            only: ['bookings', 'weekStart', 'weekEnd'],
            data: { week: weekDate },
            preserveState: true,
            preserveScroll: true,
            showProgress: false,
        });
    }

    function nextWeek() {
        const d = new Date(currentWeekStart.value);
        d.setDate(d.getDate() + 7);
        navigate(toDateString(d));
    }

    function prevWeek() {
        const d = new Date(currentWeekStart.value);
        d.setDate(d.getDate() - 7);
        navigate(toDateString(d));
    }

    function nextDay() {
        const d = new Date(currentDay.value);
        d.setDate(d.getDate() + 1);
        currentDay.value = d;
        const monday = getMonday(d);
        if (
            toDateString(monday) !==
            toDateString(new Date(currentWeekStart.value))
        ) {
            navigate(toDateString(monday));
        }
    }

    function prevDay() {
        const d = new Date(currentDay.value);
        d.setDate(d.getDate() - 1);
        currentDay.value = d;
        const monday = getMonday(d);
        if (
            toDateString(monday) !==
            toDateString(new Date(currentWeekStart.value))
        ) {
            navigate(toDateString(monday));
        }
    }

    function goToToday() {
        const today = new Date();
        currentDay.value = today;
        const monday = getMonday(today);
        navigate(toDateString(monday));
    }

    function goToDate(date: Date) {
        currentDay.value = date;
        const monday = getMonday(date);
        navigate(toDateString(monday));
        viewMode.value = 'week';
    }

    function goToDayView(date: Date) {
        currentDay.value = date;
        const monday = getMonday(date);
        if (
            toDateString(monday) !==
            toDateString(new Date(currentWeekStart.value))
        ) {
            navigate(toDateString(monday));
        }
        viewMode.value = 'day';
    }

    const weekDays = computed(() => {
        const start = new Date(currentWeekStart.value);
        return Array.from({ length: 7 }, (_, i) => {
            const d = new Date(start);
            d.setDate(d.getDate() + i);
            return d;
        });
    });

    return {
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
    };
}
