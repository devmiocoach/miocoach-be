import { router } from '@inertiajs/vue3';
import { useDocumentVisibility, useIntervalFn } from '@vueuse/core';
import { watch } from 'vue';

const POLL_INTERVAL = 5 * 60 * 1000; // 5 minutes

const RELOAD_PROPS = [
    'activeClientsCount',
    'sessionsThisWeek',
    'revenueThisMonth',
    'expiringClientsCount',
    'todaySessions',
    'expiringThisWeekCount',
    'inactiveClientsCount',
    'recentActivities',
];

export function useDashboardPolling() {
    const visibility = useDocumentVisibility();

    const { pause, resume } = useIntervalFn(() => {
        router.reload({ only: RELOAD_PROPS });
    }, POLL_INTERVAL);

    watch(visibility, (state) => {
        if (state === 'hidden') {
            pause();
        } else {
            resume();
        }
    });
}
