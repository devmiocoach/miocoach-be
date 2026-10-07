import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

import type { AgendaBooking } from '@modules/Users/resources/js/types/agenda';

const SNAP_MINUTES = 15;

export function useBookingResize(slotHeightRem: number, startHour: number) {
    const resizingBookingId = ref<number | null>(null);
    const resizeGhostHeight = ref<string | null>(null);

    let startY = 0;
    let originalDuration = 0;
    let bookingId = 0;
    let containerEl: HTMLElement | null = null;

    function getRemPerMinute(): number {
        return slotHeightRem / 60;
    }

    function onResizeStart(
        event: MouseEvent,
        booking: AgendaBooking,
        container: HTMLElement,
    ) {
        event.preventDefault();
        event.stopPropagation();

        bookingId = booking.id;
        containerEl = container;
        startY = event.clientY;
        originalDuration = booking.duration_minutes || 60;
        resizingBookingId.value = booking.id;

        const start = new Date(booking.starts_at);
        const startMinutes =
            start.getHours() * 60 + start.getMinutes() - startHour * 60;
        const topRem = startMinutes * getRemPerMinute();
        const heightRem = originalDuration * getRemPerMinute();
        resizeGhostHeight.value = `${Math.max(heightRem, SNAP_MINUTES * getRemPerMinute())}rem`;

        document.addEventListener('mousemove', onResizeMove);
        document.addEventListener('mouseup', onResizeEnd);
    }

    function onResizeMove(event: MouseEvent) {
        if (!containerEl) return;

        const deltaY = event.clientY - startY;
        const containerRect = containerEl.getBoundingClientRect();
        const remSize =
            containerRect.height /
            (((22 - startHour) * slotHeightRem) / slotHeightRem);
        const pixelsPerRem =
            containerRect.height / ((22 - startHour) * slotHeightRem);

        const deltaMinutes = deltaY / (pixelsPerRem * getRemPerMinute());
        let newDuration = originalDuration + deltaMinutes;
        newDuration = Math.round(newDuration / SNAP_MINUTES) * SNAP_MINUTES;
        newDuration = Math.max(newDuration, SNAP_MINUTES);

        resizeGhostHeight.value = `${newDuration * getRemPerMinute()}rem`;
    }

    function onResizeEnd(event: MouseEvent) {
        document.removeEventListener('mousemove', onResizeMove);
        document.removeEventListener('mouseup', onResizeEnd);

        if (!containerEl) return;

        const deltaY = event.clientY - startY;
        const containerRect = containerEl.getBoundingClientRect();
        const totalGridHeight = (22 - startHour) * slotHeightRem;
        const pixelsPerRem = containerRect.height / totalGridHeight;

        const deltaMinutes = deltaY / (pixelsPerRem * getRemPerMinute());
        let newDuration = originalDuration + deltaMinutes;
        newDuration = Math.round(newDuration / SNAP_MINUTES) * SNAP_MINUTES;
        newDuration = Math.max(newDuration, SNAP_MINUTES);

        if (newDuration !== originalDuration) {
            router.patch(
                route('coach.agenda.bookings.update', bookingId),
                { duration_minutes: newDuration },
                { preserveScroll: true },
            );
        }

        resizingBookingId.value = null;
        resizeGhostHeight.value = null;
        containerEl = null;
    }

    return {
        resizingBookingId,
        resizeGhostHeight,
        onResizeStart,
    };
}
