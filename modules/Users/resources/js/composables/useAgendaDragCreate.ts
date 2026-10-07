import { ref } from 'vue';

const SNAP_MINUTES = 15;

export interface DragCreateResult {
    date: string;
    startTime: string;
    endTime: string;
}

export function useAgendaDragCreate(slotHeightRem: number, startHour: number) {
    const isDragging = ref(false);
    const ghostTop = ref(0);
    const ghostHeight = ref(0);
    const dragDay = ref<string | null>(null);

    let containerEl: HTMLElement | null = null;
    let startSlotMinutes = 0;
    let currentMinutes = 0;
    let startY = 0;
    let dayStr = '';

    function getRemPerMinute(): number {
        return slotHeightRem / 60;
    }

    function onDragStart(event: MouseEvent, day: Date, container: HTMLElement) {
        containerEl = container;
        const rect = container.getBoundingClientRect();
        const pixelsPerRem = rect.height / ((22 - startHour) * slotHeightRem);
        const offsetY = event.clientY - rect.top;
        const totalMinutes = offsetY / pixelsPerRem / getRemPerMinute();

        startSlotMinutes =
            Math.floor(totalMinutes / SNAP_MINUTES) * SNAP_MINUTES +
            startHour * 60;
        currentMinutes = startSlotMinutes + SNAP_MINUTES;
        startY = event.clientY;
        dayStr = day.toISOString().split('T')[0];
        dragDay.value = dayStr;

        ghostTop.value =
            (startSlotMinutes - startHour * 60) * getRemPerMinute();
        ghostHeight.value = SNAP_MINUTES * getRemPerMinute();

        document.addEventListener('mousemove', onDragMove);
        document.addEventListener('mouseup', onDragEnd);
    }

    function onDragMove(event: MouseEvent) {
        if (!containerEl) return;
        const delta = event.clientY - startY;

        if (!isDragging.value && Math.abs(delta) > 8) {
            isDragging.value = true;
        }

        if (!isDragging.value) return;

        const rect = containerEl.getBoundingClientRect();
        const pixelsPerRem = rect.height / ((22 - startHour) * slotHeightRem);
        const offsetY = event.clientY - rect.top;
        const totalMinutes = offsetY / pixelsPerRem / getRemPerMinute();
        currentMinutes =
            Math.round(totalMinutes / SNAP_MINUTES) * SNAP_MINUTES +
            startHour * 60;
        currentMinutes = Math.max(
            currentMinutes,
            startSlotMinutes + SNAP_MINUTES,
        );

        ghostHeight.value =
            (currentMinutes - startSlotMinutes) * getRemPerMinute();
    }

    let resolveCallback: ((result: DragCreateResult | null) => void) | null =
        null;

    function onDragEnd() {
        document.removeEventListener('mousemove', onDragMove);
        document.removeEventListener('mouseup', onDragEnd);

        if (isDragging.value && currentMinutes > startSlotMinutes) {
            const startH = Math.floor(startSlotMinutes / 60);
            const startM = startSlotMinutes % 60;
            const endH = Math.floor(currentMinutes / 60);
            const endM = currentMinutes % 60;

            const result: DragCreateResult = {
                date: dayStr,
                startTime: `${startH.toString().padStart(2, '0')}:${startM.toString().padStart(2, '0')}`,
                endTime: `${endH.toString().padStart(2, '0')}:${endM.toString().padStart(2, '0')}`,
            };

            if (resolveCallback) resolveCallback(result);
        }

        isDragging.value = false;
        ghostTop.value = 0;
        ghostHeight.value = 0;
        dragDay.value = null;
        containerEl = null;
    }

    function startDragCreate(
        event: MouseEvent,
        day: Date,
        container: HTMLElement,
    ): Promise<DragCreateResult | null> {
        return new Promise((resolve) => {
            resolveCallback = resolve;
            onDragStart(event, day, container);
        });
    }

    return {
        isDragging,
        ghostTop,
        ghostHeight,
        dragDay,
        startDragCreate,
    };
}
