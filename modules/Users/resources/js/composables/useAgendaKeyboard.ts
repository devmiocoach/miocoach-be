import { onBeforeUnmount, onMounted } from 'vue';

interface KeyboardActions {
    goToToday: () => void;
    openNewBooking: () => void;
    nextPeriod: () => void;
    prevPeriod: () => void;
    setView: (view: 'day' | 'week' | 'month' | 'schedule') => void;
}

export function useAgendaKeyboard(actions: KeyboardActions) {
    function handleKeydown(event: KeyboardEvent) {
        const target = event.target as HTMLElement;
        if (
            target.tagName === 'INPUT' ||
            target.tagName === 'TEXTAREA' ||
            target.tagName === 'SELECT' ||
            target.isContentEditable
        ) {
            return;
        }

        if (
            event.target instanceof HTMLElement &&
            target.closest('[role="dialog"]')
        ) {
            return;
        }

        switch (event.key) {
            case 't':
                event.preventDefault();
                actions.goToToday();
                break;
            case 'n':
                event.preventDefault();
                actions.openNewBooking();
                break;
            case 'ArrowRight':
                event.preventDefault();
                actions.nextPeriod();
                break;
            case 'ArrowLeft':
                event.preventDefault();
                actions.prevPeriod();
                break;
            case '1':
                event.preventDefault();
                actions.setView('day');
                break;
            case '2':
                event.preventDefault();
                actions.setView('week');
                break;
            case '3':
                event.preventDefault();
                actions.setView('month');
                break;
            case '4':
                event.preventDefault();
                actions.setView('schedule');
                break;
        }
    }

    onMounted(() => {
        document.addEventListener('keydown', handleKeydown);
    });

    onBeforeUnmount(() => {
        document.removeEventListener('keydown', handleKeydown);
    });
}
