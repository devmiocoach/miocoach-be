import { useLocalStorage } from '@vueuse/core';
import { computed } from 'vue';

interface DismissedState {
    [key: string]: string; // key → date string (YYYY-MM-DD)
}

export function useDismissedAlerts() {
    const dismissed = useLocalStorage<DismissedState>(
        'coach-dashboard:dismissed-alerts',
        {},
    );

    const today = new Date().toISOString().slice(0, 10);

    function isDismissed(key: string): boolean {
        return dismissed.value[key] === today;
    }

    function dismiss(key: string): void {
        dismissed.value = { ...dismissed.value, [key]: today };
    }

    const isExpiringDismissed = computed(() => isDismissed('expiring'));
    const isInactiveDismissed = computed(() => isDismissed('inactive'));

    function dismissExpiring() {
        dismiss('expiring');
    }

    function dismissInactive() {
        dismiss('inactive');
    }

    return {
        isExpiringDismissed,
        isInactiveDismissed,
        dismissExpiring,
        dismissInactive,
    };
}
